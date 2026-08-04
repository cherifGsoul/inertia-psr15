<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use JsonSerializable;

use function explode;
use function is_array;

final class Page implements JsonSerializable
{
    /**
     * @param array<string, mixed> $props
     */
    private function __construct(
        private ?string $component = null,
        private array $props = [],
        private ?string $url = null,
        private ?string $version = null,
        private bool $encryptHistory = false,
        private bool $clearHistory = false,
        private bool $preserveFragment = false,
        /** @var list<string> */
        private array $mergeProps = [],
        /** @var list<string> */
        private array $prependProps = [],
        /** @var list<string> */
        private array $deepMergeProps = [],
        /** @var list<string> */
        private array $matchPropsOn = [],
        /** @var array<string, array<string, null|bool|int|string>> */
        private array $scrollProps = [],
        /** @var array<string, list<string>> */
        private array $deferredProps = [],
        /** @var list<string> */
        private array $rescuedProps = [],
        /** @var list<string> */
        private array $sharedProps = [],
        /** @var array<string, array{prop: string, expiresAt: ?int}> */
        private array $onceProps = []
    ) {}

    /**
     * @param array<string, mixed> $props
     */
    public static function from(string $component, array $props = [], ?string $url = null, ?string $version = null): self
    {
        return new Page($component, $props, $url, $version);
    }

    public static function create(): Page
    {
        return new Page();
    }

    public function getComponent(): string
    {
        return $this->component ?? '';
    }

    public function withComponent(string $component): self
    {
        $page            = clone $this;
        $page->component = $component;

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * @param array<string, mixed> $props
     */
    public function withProps(array $props): self
    {
        $page        = clone $this;
        $page->props = self::mergeProps($page->props, $this->unpackProps($props));

        return $page;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function withUrl(string $url): self
    {
        $page      = clone $this;
        $page->url = $url;

        return $page;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function withVersion(string $version): self
    {
        $page          = clone $this;
        $page->version = $version;

        return $page;
    }

    public function encryptHistory(bool $enabled = true): self
    {
        $page                 = clone $this;
        $page->encryptHistory = $enabled;

        return $page;
    }

    public function clearHistory(bool $enabled = true): self
    {
        $page               = clone $this;
        $page->clearHistory = $enabled;

        return $page;
    }

    public function preserveFragment(bool $enabled = true): self
    {
        $page                    = clone $this;
        $page->preserveFragment  = $enabled;

        return $page;
    }

    /** @param list<string> $props */
    public function withMergeProps(array $props): self
    {
        $page             = clone $this;
        $page->mergeProps = $props;

        return $page;
    }

    /** @param list<string> $props */
    public function withPrependProps(array $props): self
    {
        $page               = clone $this;
        $page->prependProps = $props;

        return $page;
    }

    /** @param list<string> $props */
    public function withDeepMergeProps(array $props): self
    {
        $page                 = clone $this;
        $page->deepMergeProps = $props;

        return $page;
    }

    /** @param list<string> $props */
    public function withMatchPropsOn(array $props): self
    {
        $page                = clone $this;
        $page->matchPropsOn  = $props;

        return $page;
    }

    /** @param array<string, array<string, null|bool|int|string>> $props */
    public function withScrollProps(array $props): self
    {
        $page               = clone $this;
        $page->scrollProps  = $props;

        return $page;
    }

    /** @param array<string, list<string>> $props */
    public function withDeferredProps(array $props): self
    {
        $page                 = clone $this;
        $page->deferredProps  = $props;

        return $page;
    }

    /** @param list<string> $props */
    public function withRescuedProps(array $props): self
    {
        $page                = clone $this;
        $page->rescuedProps  = $props;

        return $page;
    }

    /** @param list<string> $props */
    public function withSharedProps(array $props): self
    {
        $page               = clone $this;
        $page->sharedProps  = $props;

        return $page;
    }

    /** @param array<string, array{prop: string, expiresAt: ?int}> $props */
    public function withOnceProps(array $props): self
    {
        $page             = clone $this;
        $page->onceProps  = $props;

        return $page;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $page = [
            'component' => $this->component,
            'props'     => $this->props,
            'url'       => $this->url,
            'version'   => $this->version,
        ];

        foreach ([
            'encryptHistory'   => $this->encryptHistory,
            'clearHistory'     => $this->clearHistory,
            'preserveFragment' => $this->preserveFragment,
            'mergeProps'       => $this->mergeProps,
            'prependProps'     => $this->prependProps,
            'deepMergeProps'   => $this->deepMergeProps,
            'matchPropsOn'     => $this->matchPropsOn,
            'scrollProps'      => $this->scrollProps,
            'deferredProps'    => $this->deferredProps,
            'rescuedProps'     => $this->rescuedProps,
            'sharedProps'      => $this->sharedProps,
            'onceProps'        => $this->onceProps,
        ] as $key => $value) {
            if (false !== $value && [] !== $value) {
                $page[$key] = $value;
            }
        }

        return $page;
    }

    public function addProp(string $key, mixed $value = null): Page|static
    {
        $page        = clone $this;
        $page->props = self::mergeProps($page->props, $this->unpackProps([
            $key => $value,
        ]));

        return $page;
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
            $current = is_array($value) ? self::mergeProps($current, $value) : $value;
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
    private static function mergeProps(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            if (isset($left[$key]) && is_array($left[$key]) && is_array($value)) {
                $left[$key] = self::mergeProps($left[$key], $value);
            } else {
                $left[$key] = $value;
            }
        }

        return $left;
    }
}
