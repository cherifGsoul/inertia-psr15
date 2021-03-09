<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Factory;

use Cherif\InertiaPsr15\Service\InertiaFactory;
use Cherif\InertiaPsr15\Service\RootViewProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class InertiaFactoryFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);
        $streamFactory = $container->get(StreamFactoryInterface::class);
        $rootViewProvider = $container->get(RootViewProviderInterface::class);

        return new InertiaFactory($responseFactory, $streamFactory, $rootViewProvider);
    }
}