<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Service;

use Psr\Http\Message\ResponseInterface;
use Sirix\InertiaPsr15\Model\OnceProp;

interface InertiaInterface
{
    /**
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props = [], ?string $url = null): ResponseInterface;

    public function version(string $version): void;

    public function share(string $key, mixed $value = null): void;

    public function shareOnce(string $key, mixed $value): OnceProp;

    public function getVersion(): ?string;

    public function encryptHistory(bool $enabled = true): void;

    public function clearHistory(bool $enabled = true): void;

    public function preserveFragment(bool $enabled = true): void;

    public function location(ResponseInterface|string $destination, int $status = 302): ResponseInterface;
}
