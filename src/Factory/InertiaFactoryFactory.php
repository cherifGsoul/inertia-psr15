<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\InertiaPsr15\Service\InertiaFactory;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;

class InertiaFactoryFactory
{
    /**
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve a service
     * @throws InvalidContainerServiceException when a resolved service has an unexpected type
     * @throws MissingContainerServiceException when a required service is not registered
     */
    public function __invoke(ContainerInterface $container): InertiaFactory
    {
        $resolver = ContainerResolver::forFactory($container, self::class);

        return new InertiaFactory(
            $resolver->get(ResponseFactoryInterface::class),
            $resolver->get(StreamFactoryInterface::class),
            $resolver->get(RootViewProviderInterface::class),
        );
    }
}
