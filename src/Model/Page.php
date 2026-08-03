<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use JsonSerializable;

use function array_merge;

final class Page implements JsonSerializable
{
    /**
     * @param array<string, mixed> $props
     */
    private function __construct(
        private ?string $component = null,
        private array $props = [],
        private ?string $url = null,
        private ?string $version = null
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
        $page->props = array_merge($page->props, $props);

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

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'component' => $this->component,
            'props'     => $this->props,
            'url'       => $this->url,
            'version'   => $this->version,
        ];
    }

    public function addProp(string $key, mixed $value = null): Page|static
    {
        $page              = clone $this;
        $page->props[$key] = $value;

        return $page;
    }
}
