<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Service;

use Closure;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sirix\InertiaPsr15\Model\OptionalProp;
use Sirix\InertiaPsr15\Model\Page;

use function array_key_exists;
use function explode;
use function is_array;
use function json_encode;
use function trim;

class Inertia implements InertiaInterface
{
    private const PARTIAL_RELOAD_STANDARD = 'standard';
    private const PARTIAL_RELOAD_ONLY     = 'only';
    private const PARTIAL_RELOAD_EXCEPT   = 'except';

    private readonly RootViewProviderInterface $rootViewProvider;
    private Page $page;

    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        RootViewProviderInterface $rootViewProvider
    ) {
        $this->rootViewProvider = $rootViewProvider;
        $this->page             = Page::create();
    }

    /**
     * @param array<string, mixed> $props
     *
     * @throws JsonException
     */
    public function render(string $component, array $props = [], ?string $url = null): ResponseInterface
    {
        $this->page = $this->page
            ->withComponent($component)
            ->withUrl($url ?? (string) $this->request->getUri())
        ;

        $partialReload = $this->partialReloadContext($component);

        if (self::PARTIAL_RELOAD_ONLY === $partialReload['mode']) {
            $props = $this->onlyProps($props, $partialReload['paths']);
        } elseif (self::PARTIAL_RELOAD_EXCEPT === $partialReload['mode']) {
            $props = $this->exceptProps($props, $partialReload['paths']);
        }

        $props = $this->resolveProps($props, self::PARTIAL_RELOAD_ONLY === $partialReload['mode']);

        $this->page = $this->page->withProps($props);

        if ($this->request->hasHeader('X-Inertia')) {
            $json = json_encode($this->page, JSON_THROW_ON_ERROR);

            return $this->createResponse($json, 'application/json');
        }

        $rootViewProvider = $this->rootViewProvider;
        $html             = $rootViewProvider($this->page);

        return $this->createResponse($html, 'text/html; charset=UTF-8');
    }

    public function version(string $version): void
    {
        $this->page = $this->page->withVersion($version);
    }

    public function share(string $key, mixed $value = null): void
    {
        $this->page = $this->page->addProp($key, $value);
    }

    public function getVersion(): ?string
    {
        return $this->page->getVersion();
    }

    public static function optional(callable $callable): OptionalProp
    {
        return new OptionalProp($callable);
    }

    public function location(ResponseInterface|string $destination, int $status = 302): ResponseInterface
    {
        $response = $this->createResponse('', 'text/html; charset=UTF-8');

        // We check if InertiaMiddleware has set up the 'X-Inertia-Location' header, so we handle the response accordingly
        if ($this->request->hasHeader('X-Inertia')) {
            $response = $response->withStatus(409);

            return $response->withHeader(
                'X-Inertia-Location',
                $destination instanceof ResponseInterface ? $destination->getHeaderLine('Location') : $destination
            );
        }

        if ($destination instanceof ResponseInterface) {
            return $destination;
        }

        $response = $response->withStatus($status);

        return $response->withHeader('Location', $destination);
    }

    private function createResponse(string $data, string $contentType): ResponseInterface
    {
        $stream = $this->streamFactory->createStream($data);

        return $this->responseFactory->createResponse()
            ->withBody($stream)
            ->withHeader('Content-Type', $contentType)
        ;
    }

    /** @return array{mode: string, paths: list<string>} */
    private function partialReloadContext(string $component): array
    {
        if (
            ! $this->request->hasHeader('X-Inertia')
            || $this->request->getHeaderLine('X-Inertia-Partial-Component') !== $component
        ) {
            return [
                'mode'  => self::PARTIAL_RELOAD_STANDARD,
                'paths' => [],
            ];
        }

        $except = $this->headerValues('X-Inertia-Partial-Except');
        if ([] !== $except) {
            return [
                'mode'  => self::PARTIAL_RELOAD_EXCEPT,
                'paths' => $except,
            ];
        }

        $only = $this->headerValues('X-Inertia-Partial-Data');
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
    private function headerValues(string $header): array
    {
        if (! $this->request->hasHeader($header)) {
            return [];
        }

        $values = [];

        foreach (explode(',', $this->request->getHeaderLine($header)) as $value) {
            $value = trim($value);

            if ('' !== $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $props
     * @param list<string>         $paths
     *
     * @return array<string, mixed>
     */
    private function onlyProps(array $props, array $paths): array
    {
        $selected    = [];
        $nestedPaths = [];

        foreach ($paths as $path) {
            if (array_key_exists($path, $props)) {
                $selected[$path] = $props[$path];

                continue;
            }

            $segments = explode('.', $path, 2);

            if (! isset($segments[1])) {
                continue;
            }

            $nestedPaths[$segments[0]][] = $segments[1];
        }

        foreach ($nestedPaths as $key => $paths) {
            if (array_key_exists($key, $selected)) {
                continue;
            }

            if (! array_key_exists($key, $props)) {
                continue;
            }

            $prop = $this->resolveOnlyContainer($props[$key]);

            if (! is_array($prop)) {
                continue;
            }

            $nested = $this->onlyProps($prop, $paths);

            if ([] !== $nested) {
                $selected[$key] = $nested;
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
        $nestedPaths = [];

        foreach ($paths as $path) {
            if (array_key_exists($path, $props)) {
                unset($props[$path]);

                continue;
            }

            $segments = explode('.', $path, 2);

            if (isset($segments[1])) {
                $nestedPaths[$segments[0]][] = $segments[1];
            }
        }

        foreach ($nestedPaths as $key => $paths) {
            if (! array_key_exists($key, $props)) {
                continue;
            }

            while ($props[$key] instanceof Closure) {
                $props[$key] = ($props[$key])();
            }

            if (is_array($props[$key])) {
                $props[$key] = $this->exceptProps($props[$key], $paths);
            }
        }

        return $props;
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function resolveProps(array $props, bool $includeOptionalProps): array
    {
        foreach ($props as $key => $prop) {
            $omit = false;
            $prop = $this->resolveProp($prop, $includeOptionalProps, $omit);

            if ($omit) {
                unset($props[$key]);

                continue;
            }

            $props[$key] = $prop;
        }

        return $props;
    }

    private function resolveProp(mixed $prop, bool $includeOptionalProps, bool &$omit): mixed
    {
        $omit = false;

        if ($prop instanceof OptionalProp) {
            if (! $includeOptionalProps) {
                $omit = true;

                return null;
            }

            return $this->resolveProp($prop(), true, $omit);
        }

        if ($prop instanceof Closure) {
            return $this->resolveProp($prop(), $includeOptionalProps, $omit);
        }

        if (! is_array($prop)) {
            return $prop;
        }

        return $this->resolveProps($prop, $includeOptionalProps);
    }

    private function resolveOnlyContainer(mixed $prop): mixed
    {
        while ($prop instanceof Closure || $prop instanceof OptionalProp) {
            $prop = $prop();
        }

        return $prop;
    }
}
