<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaFactory;
use Sirix\InertiaPsr15\Service\RootViewProviderInterface;

class InertiaFactoryFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): InertiaFactory
    {
        $responseFactory  = $container->get(ResponseFactoryInterface::class);
        $streamFactory    = $container->get(StreamFactoryInterface::class);
        $rootViewProvider = $container->get(RootViewProviderInterface::class);

        return new InertiaFactory($responseFactory, $streamFactory, $rootViewProvider);
    }
}
