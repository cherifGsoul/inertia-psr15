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
    private bool $encryptHistory = false;
    private bool $clearHistory = false;
    private array $mergeProps = [];
    private array $prependProps = [];
    private array $deepMergeProps = [];
    private array $matchPropsOn = [];
    private array $scrollProps = [];
    private array $deferredProps = [];
    private array $onceProps = [];

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

    public function getComponent(): ?string
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

    public function withEncryptHistory(bool $encryptHistory): self
    {
        $page = clone $this;
        $page->encryptHistory = $encryptHistory;
        return $page;
    }

    public function withClearHistory(bool $clearHistory): self
    {
        $page = clone $this;
        $page->clearHistory = $clearHistory;
        return $page;
    }

    public function withMergeProps(array $mergeProps): self
    {
        $page = clone $this;
        $page->mergeProps = $mergeProps;
        return $page;
    }

    public function withPrependProps(array $prependProps): self
    {
        $page = clone $this;
        $page->prependProps = $prependProps;
        return $page;
    }

    public function withDeepMergeProps(array $deepMergeProps): self
    {
        $page = clone $this;
        $page->deepMergeProps = $deepMergeProps;
        return $page;
    }

    public function withMatchPropsOn(array $matchPropsOn): self
    {
        $page = clone $this;
        $page->matchPropsOn = $matchPropsOn;
        return $page;
    }

    public function withScrollProps(array $scrollProps): self
    {
        $page = clone $this;
        $page->scrollProps = $scrollProps;
        return $page;
    }

    public function withDeferredProps(array $deferredProps): self
    {
        $page = clone $this;
        $page->deferredProps = $deferredProps;
        return $page;
    }

    public function withOnceProps(array $onceProps): self
    {
        $page = clone $this;
        $page->onceProps = $onceProps;
        return $page;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = [
            'component' => $this->getComponent(),
            'props' => $this->getProps(),
            'url' => $this->getUrl(),
            'version' => $this->getVersion(),
            'encryptHistory' => $this->encryptHistory,
            'clearHistory' => $this->clearHistory,
        ];

        if (!empty($this->mergeProps)) {
            $data['mergeProps'] = $this->mergeProps;
        }

        if (!empty($this->prependProps)) {
            $data['prependProps'] = $this->prependProps;
        }

        if (!empty($this->deepMergeProps)) {
            $data['deepMergeProps'] = $this->deepMergeProps;
        }

        if (!empty($this->matchPropsOn)) {
            $data['matchPropsOn'] = $this->matchPropsOn;
        }

        if (!empty($this->scrollProps)) {
            $data['scrollProps'] = $this->scrollProps;
        }

        if (!empty($this->deferredProps)) {
            $data['deferredProps'] = $this->deferredProps;
        }

        if (!empty($this->onceProps)) {
            $data['onceProps'] = $this->onceProps;
        }

        return $data;
    }

    public function addProp(string $key, $value = null)
    {
        $page = clone $this;
        $page->props[$key] = $value;
        return $page;
    }
}
