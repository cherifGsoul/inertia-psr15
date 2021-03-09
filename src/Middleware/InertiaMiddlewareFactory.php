<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Middleware;

use Cherif\InertiaPsr15\Service\InertiaFactoryInterface;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

class InertiaMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        if (!$container->has(InertiaFactoryInterface::class)) {
            throw new InvalidArgumentException('Inertia service factory implementation is missing!');
        }
        $inertiaFactory = $container->get(InertiaFactoryInterface::class);
        return new InertiaMiddleware($inertiaFactory);
    }
}