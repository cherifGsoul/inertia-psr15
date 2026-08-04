<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Factory;

use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;

class InertiaMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): InertiaMiddleware
    {
        if (! $container->has(InertiaFactoryInterface::class)) {
            throw new InvalidArgumentException('Inertia service factory implementation is missing!');
        }
        $inertiaFactory = $container->get(InertiaFactoryInterface::class);

        return new InertiaMiddleware($inertiaFactory);
    }
}
