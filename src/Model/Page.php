<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Model;

use JsonSerializable;

final class Page implements JsonSerializable
{
    private ?string $component;
    private array $props;
    private ?string $url;
    private ?string $version;

    private function __construct(?string $component = null, array $props = [], ?string $url = null, ?string $version = null)
    {
        $this->component = $component;
        $this->props = $props;
        $this->url = $url;
        $this->version = $version;
    }

    public static function from (string $component, array $props = [], ?string $url = null, ?string $version = null): self
    {
        return new Page($component, $props, $url, $version);
    }

    public static function create()
    {
        return new Page();
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function withComponent(string $component): self
    {
        $page = clone $this;
        $page->component = $component;
        return $page;
    }


    public function getProps(): array
    {
        return $this->props;
    }

    public function withProps(array $props): self
    {
        $page = clone $this;
        $page->props = array_merge($page->props, $props);
        return $page;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function withUrl(string $url): self
    {
        $page = clone $this;
        $page->url = $url;
        return $page;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function withVersion(string $version): self
    {
        $page = clone $this;
        $page->version = $version;
        return $page;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'component' => $this->getComponent(),
            'props' => $this->getProps(),
            'url' => $this->getUrl(),
            'version' => $this->getVersion(),
        ];
    }

    public function addProp(string $key, $value = null)
    {
        $page = clone $this;
        $page->props[$key] = $value;
        return $page;
    }
}
