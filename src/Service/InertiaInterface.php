<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Psr\Http\Message\ResponseInterface as Response;

interface InertiaInterface
{
    public function render(string $component, array $props = [], ?string $url = null): Response;
    public function version(string $version);
    public function share(string $key, $value = null);
    public function getVersion();
    public function defer(string $key, callable $callback, string $group = 'default'): self;
    public function merge(string $key, $value): self;
    public function prepend(string $key, $value): self;
    public function deepMerge(string $key, $value): self;
    public function once(string $key, $value): self;
    public function encryptHistory(bool $encryptHistory = true): self;
    public function clearHistory(bool $clearHistory = true): self;
}