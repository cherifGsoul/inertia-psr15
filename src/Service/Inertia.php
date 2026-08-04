<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Service;

use Closure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sirix\InertiaPsr15\Model\DeferredProp;
use Sirix\InertiaPsr15\Model\MergeProp;
use Sirix\InertiaPsr15\Model\OnceProp;
use Sirix\InertiaPsr15\Model\OptionalProp;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Model\Prop;
use Sirix\InertiaPsr15\Model\ProvidesScrollMetadata;
use Sirix\InertiaPsr15\Model\ScrollProp;
use Sirix\InertiaPsr15\Service\Internal\PropResolutionState;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;
use stdClass;
use Throwable;

use function array_key_exists;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function is_array;
use function json_encode;
use function preg_match;
use function strcasecmp;
use function strlen;
use function trim;

class Inertia implements InertiaInterface
{
    private const PARTIAL_RELOAD_STANDARD = 'standard';
    private const PARTIAL_RELOAD_ONLY     = 'only';
    private const PARTIAL_RELOAD_EXCEPT   = 'except';

    private Page $page;

    /** @var list<string> */
    private array $sharedKeys = [];

    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RootViewProviderInterface $rootViewProvider
    ) {
        $this->page = Page::create();
    }

    /** @param array<string, mixed> $props */
    public function render(string $component, array $props = [], ?string $url = null): ResponseInterface
    {
        $props      = $this->mergeArrays($this->page->getProps(), $this->unpackProps($props));
        $this->page = $this->page
            ->withComponent($component)
            ->withUrl($url ?? $this->requestUrl())
            ->withSharedProps(array_values(array_unique($this->sharedKeys)))
        ;

        $partial = $this->partialReloadContext($component);
        if (self::PARTIAL_RELOAD_ONLY === $partial['mode']) {
            $props = $this->onlyProps($props, $partial['paths']);
        } elseif (self::PARTIAL_RELOAD_EXCEPT === $partial['mode']) {
            $props = $this->exceptProps($props, $partial['paths']);
        }

        $state = new PropResolutionState(
            self::PARTIAL_RELOAD_ONLY === $partial['mode'],
            self::PARTIAL_RELOAD_STANDARD !== $partial['mode'],
            self::PARTIAL_RELOAD_ONLY === $partial['mode'] ? $partial['paths'] : [],
            self::PARTIAL_RELOAD_EXCEPT === $partial['mode'] ? $partial['paths'] : [],
            $this->headerValues('X-Inertia-Reset'),
            $this->headerValues('X-Inertia-Except-Once-Props'),
            $this->requestHeader('X-Inertia-Infinite-Scroll-Merge-Intent')
        );

        $props      = $this->resolveProps($props, '', $state);
        $props      = [
            'errors' => new stdClass(),
            ...$props,
        ];
        $this->page = $this->page->withProps($props);
        $this->applyMetadata($state);

        if ($this->request->hasHeader('X-Inertia')) {
            return $this->createResponse(json_encode($this->page, JSON_THROW_ON_ERROR), 'application/json');
        }

        return $this->createResponse(($this->rootViewProvider)($this->page), 'text/html; charset=UTF-8');
    }

    public function version(string $version): void
    {
        $this->page = $this->page->withVersion($version);
    }

    public function share(string $key, mixed $value = null): void
    {
        $this->assertSafePropPath($key, 'Shared prop key');
        $this->sharedKeys[] = explode('.', $key)[0];
        $this->page         = $this->page->addProp($key, $value);
    }

    public function shareOnce(string $key, mixed $value): OnceProp
    {
        $this->assertSafePropPath($key, 'Shared once prop key');
        $prop = $value instanceof OnceProp ? $value : self::once($value);
        $this->share($key, $prop);

        return $prop;
    }

    public function getVersion(): ?string
    {
        return $this->page->getVersion();
    }

    public function encryptHistory(bool $enabled = true): void
    {
        $this->page = $this->page->encryptHistory($enabled);
    }

    public function clearHistory(bool $enabled = true): void
    {
        $this->page = $this->page->clearHistory($enabled);
    }

    public function preserveFragment(bool $enabled = true): void
    {
        $this->page = $this->page->preserveFragment($enabled);
    }

    public static function optional(callable $callable): OptionalProp
    {
        return new OptionalProp($callable);
    }

    public static function defer(callable $resolver, ?string $group = null, bool $rescue = false): DeferredProp
    {
        if (null !== $group) {
            self::assertSafeStaticPath($group, 'Deferred group');
        }

        return new DeferredProp(Closure::fromCallable($resolver), $group, $rescue);
    }

    public static function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    public static function deepMerge(mixed $value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /** @param null|array<string, mixed>|Closure(mixed): ProvidesScrollMetadata|ProvidesScrollMetadata $metadata */
    public static function scroll(
        mixed $value,
        string $wrapper = 'data',
        array|Closure|ProvidesScrollMetadata|null $metadata = null
    ): ScrollProp {
        return new ScrollProp($value, $wrapper, $metadata);
    }

    public static function once(mixed $value): OnceProp
    {
        return new OnceProp($value);
    }

    public function location(ResponseInterface|string $destination, int $status = 302): ResponseInterface
    {
        $response = $this->createResponse('', 'text/html; charset=UTF-8');
        if ($this->request->hasHeader('X-Inertia')) {
            $location = $destination instanceof ResponseInterface ? $destination->getHeaderLine('Location') : $destination;
            $this->assertSafeRedirectLocation($location);

            return $response->withStatus(409)->withHeader(
                'X-Inertia-Location',
                $location
            );
        }

        if ($destination instanceof ResponseInterface) {
            return $destination;
        }

        $this->assertSafeRedirectLocation($destination);

        return $response->withStatus($status)->withHeader('Location', $destination);
    }

    private function createResponse(string $data, string $contentType): ResponseInterface
    {
        return $this->responseFactory->createResponse()
            ->withBody($this->streamFactory->createStream($data))
            ->withHeader('Content-Type', $contentType)
        ;
    }

    /** @return array{mode: string, paths: list<string>} */
    private function partialReloadContext(string $component): array
    {
        if (! $this->request->hasHeader('X-Inertia') || $this->request->getHeaderLine('X-Inertia-Partial-Component') !== $component) {
            return [
                'mode'  => self::PARTIAL_RELOAD_STANDARD,
                'paths' => [],
            ];
        }

        $except = $this->headerValues('X-Inertia-Partial-Except', true);
        if ([] !== $except) {
            return [
                'mode'  => self::PARTIAL_RELOAD_EXCEPT,
                'paths' => $except,
            ];
        }

        $only = $this->headerValues('X-Inertia-Partial-Data', true);
        if ([] !== $only) {
            return [
                'mode'  => self::PARTIAL_RELOAD_ONLY,
                'paths' => $only,
            ];
        }

        return [
            'mode'  => self::PARTIAL_RELOAD_STANDARD,
            'paths' => [],
        ];
    }

    /** @return list<string> */
    private function headerValues(string $header, bool $readHeaderLine = false): array
    {
        if ($readHeaderLine) {
            if (! $this->request->hasHeader($header)) {
                return [];
            }

            $line = $this->request->getHeaderLine($header);
        } else {
            $line = '';
            foreach ($this->request->getHeaders() as $name => $values) {
                if (0 === strcasecmp($name, $header)) {
                    $line = implode(',', $values);

                    break;
                }
            }
        }

        if ('' === $line) {
            return [];
        }

        $values = [];
        foreach (explode(',', $line) as $value) {
            $value = trim($value);
            if ('' !== $value && $this->isSafePath($value)) {
                $values[$value] = $value;
            }
        }

        return array_values($values);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<string>         $paths
     *
     * @return array<string, mixed>
     */
    private function onlyProps(array $props, array $paths): array
    {
        $selected = [];
        $nested   = [];
        foreach ($paths as $path) {
            if (array_key_exists($path, $props)) {
                $selected[$path] = $props[$path];

                continue;
            }
            $parts = explode('.', $path, 2);
            $head  = $parts[0];
            $tail  = $parts[1] ?? null;
            if (null !== $tail) {
                $nested[$head][] = $tail;
            }
        }

        foreach ($nested as $key => $children) {
            if (array_key_exists($key, $selected)) {
                continue;
            }

            if (! array_key_exists($key, $props)) {
                continue;
            }

            if ($props[$key] instanceof Prop) {
                $selected[$key] = $props[$key];

                continue;
            }
            $value = $this->resolveContainer($props[$key]);
            if (is_array($value)) {
                $value = $this->onlyProps($value, $children);
                if ([] !== $value) {
                    $selected[$key] = $value;
                }
            }
        }

        return $selected;
    }

    /**
     * @param array<string, mixed> $props
     * @param list<string>         $paths
     *
     * @return array<string, mixed>
     */
    private function exceptProps(array $props, array $paths): array
    {
        $nested = [];
        foreach ($paths as $path) {
            if (array_key_exists($path, $props)) {
                unset($props[$path]);

                continue;
            }
            $parts = explode('.', $path, 2);
            $head  = $parts[0];
            $tail  = $parts[1] ?? null;
            if (null !== $tail) {
                $nested[$head][] = $tail;
            }
        }

        foreach ($nested as $key => $children) {
            if (! array_key_exists($key, $props)) {
                continue;
            }

            if ($props[$key] instanceof Prop) {
                continue;
            }

            $value = $this->resolveContainer($props[$key]);
            if (is_array($value)) {
                $props[$key] = $this->exceptProps($value, $children);
            }
        }

        return $props;
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function resolveProps(array $props, string $basePath, PropResolutionState $state): array
    {
        foreach ($props as $key => $value) {
            $path  = '' === $basePath ? (string) $key : $basePath . '.' . $key;
            $omit  = false;
            $value = $this->resolveProp($value, $path, $state, $omit);
            if ($omit) {
                unset($props[$key]);
            } else {
                $props[$key] = $value;
            }
        }

        return $props;
    }

    private function resolveProp(mixed $value, string $path, PropResolutionState $state, bool &$omit): mixed
    {
        $omit            = false;
        $optional        = false;
        $deferred        = null;
        $once            = null;
        $mergeOperations = [];
        $scroll          = null;

        while ($value instanceof Prop) {
            if ($value instanceof OptionalProp) {
                $optional = true;
            } elseif ($value instanceof DeferredProp) {
                $deferred = $value;
            } elseif ($value instanceof OnceProp) {
                $once = $value;
            } elseif ($value instanceof MergeProp) {
                $mergeOperations = [...$mergeOperations, ...$value->operations()];
            } elseif ($value instanceof ScrollProp) {
                $scroll = $value;
            }
            $value = $value->value();
        }

        $this->registerOnceMetadata($once, $path, $state);

        if ($once) {
            $onceKey = $once->key() ?? $path;
            if (! $once->isFresh() && ! $state->explicitlyRequested($path) && $state->exceptOnce($onceKey)) {
                $omit = true;

                return null;
            }
        }

        if (($optional || $deferred) && ! $state->explicitOnly && ! $state->isPartial) {
            if ($deferred) {
                $state->deferred[$deferred->group()][] = $path;
            }
            $this->registerMergeMetadata($path, $mergeOperations, $state);
            $omit = true;

            return null;
        }

        try {
            while ($value instanceof Closure) {
                $value = $value();
            }
        } catch (Throwable $exception) {
            if ($deferred && $deferred->rescue()) {
                $state->rescued[] = $path;
                $omit             = true;

                return null;
            }

            throw $exception;
        }

        if (is_array($value)) {
            $value = $this->resolveProps($value, $path, $state);
            $value = $this->exceptProps($value, $state->excludedChildren($path));
        }

        if ($scroll) {
            $state->scroll[$path] = $scroll->metadata($value);
            if ($state->isReset($path)) {
                $state->scroll[$path]['reset'] = true;
            }
            $intent               = $state->scrollMergeIntent;
            $mergeOperations[]    = [
                'mode'    => 'prepend' === $intent ? 'prepend' : 'append',
                'path'    => $scroll->wrapper(),
                'matchOn' => null,
            ];
        }

        $this->registerMergeMetadata($path, $mergeOperations, $state);

        return $value;
    }

    private function resolveContainer(mixed $value): mixed
    {
        while ($value instanceof Prop) {
            $value = $value->value();
        }

        while ($value instanceof Closure) {
            $value = $value();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function unpackProps(array $props): array
    {
        $unpacked = [];
        foreach ($props as $key => $value) {
            $segments = explode('.', (string) $key);
            $current  = &$unpacked;
            foreach ($segments as $segment) {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
            $current = is_array($value) ? $this->mergeArrays($current, $value) : $value;
            unset($current);
        }

        return $unpacked;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array<string, mixed>
     */
    private function mergeArrays(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            if (isset($left[$key]) && is_array($left[$key]) && is_array($value)) {
                $left[$key] = $this->mergeArrays($left[$key], $value);
            } else {
                $left[$key] = $value;
            }
        }

        return $left;
    }

    private function registerOnceMetadata(?OnceProp $once, string $path, PropResolutionState $state): void
    {
        if ($once) {
            $state->once[$once->key() ?? $path] = [
                'prop'      => $path,
                'expiresAt' => $once->expiresAt(),
            ];
        }
    }

    /** @param list<array{mode: 'append'|'deep'|'prepend', path: string, matchOn: ?string}> $operations */
    private function registerMergeMetadata(string $path, array $operations, PropResolutionState $state): void
    {
        if ($state->isReset($path)) {
            return;
        }

        foreach ($operations as $operation) {
            $target = '' === $operation['path'] ? $path : $path . '.' . $operation['path'];
            if ('prepend' === $operation['mode']) {
                $state->prepend[] = $target;
            } elseif ('deep' === $operation['mode']) {
                $state->deepMerge[] = $target;
            } else {
                $state->merge[] = $target;
            }
            if (null !== $operation['matchOn']) {
                $state->matchOn[] = $target . '.' . $operation['matchOn'];
            }
        }
    }

    private function applyMetadata(PropResolutionState $state): void
    {
        $this->page = $this->page
            ->withDeferredProps($state->deferred)
            ->withRescuedProps(array_values(array_unique($state->rescued)))
            ->withOnceProps($state->once)
            ->withMergeProps(array_values(array_unique($state->merge)))
            ->withPrependProps(array_values(array_unique($state->prepend)))
            ->withDeepMergeProps(array_values(array_unique($state->deepMerge)))
            ->withMatchPropsOn(array_values(array_unique($state->matchOn)))
            ->withScrollProps($state->scroll)
        ;
    }

    private function requestUrl(): string
    {
        $uri   = $this->request->getUri();
        $path  = $uri->getPath();
        $query = $uri->getQuery();
        if ('' !== $path || '' !== $query) {
            return ('' === $path ? '/' : $path) . ('' === $query ? '' : '?' . $query);
        }

        return (string) $uri;
    }

    private function requestHeader(string $header): string
    {
        foreach ($this->request->getHeaders() as $name => $values) {
            if (0 === strcasecmp($name, $header)) {
                return implode(',', $values);
            }
        }

        return '';
    }

    private function isSafePath(string $path): bool
    {
        return 255 >= strlen($path)
            && 1 === preg_match('/^[^.\x00-\x1F\x7F]+(?:\.[^.\x00-\x1F\x7F]+)*$/', $path);
    }

    private function assertSafePropPath(string $path, string $label): void
    {
        if (! $this->isSafePath($path)) {
            throw new InvalidArgumentException($label . ' must be a non-empty safe dot path.');
        }
    }

    private static function assertSafeStaticPath(string $path, string $label): void
    {
        if (255 < strlen($path) || 1 !== preg_match('/^[^.\x00-\x1F\x7F]+(?:\.[^.\x00-\x1F\x7F]+)*$/', $path)) {
            throw new InvalidArgumentException($label . ' must be a non-empty safe dot path.');
        }
    }

    private function assertSafeRedirectLocation(string $location): void
    {
        if ('' === $location || 8192 < strlen($location) || 1 === preg_match('/[\x00-\x1F\x7F]/', $location)) {
            throw new InvalidArgumentException('Redirect location must be a non-empty header-safe URI.');
        }
    }
}
