<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Psr\Http\Message\ResponseInterface as Response;

interface InertiaInterface
{
    public function render(string $component, array $props = []): Response;
    public function version(string $version);
    public function share(string $key, $value = null);
    public function getVersion();
}