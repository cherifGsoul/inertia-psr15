<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15;

use Cherif\InertiaPsr15\Factory\InertiaFactoryFactory;
use Cherif\InertiaPsr15\Factory\RootViewProviderFactory;
use Cherif\InertiaPsr15\Middleware\InertiaMiddleware;
use Cherif\InertiaPsr15\Middleware\InertiaMiddlewareFactory;
use Cherif\InertiaPsr15\Service\InertiaFactoryInterface;
use Cherif\InertiaPsr15\Service\RootViewProviderInterface;

/**
 * The configuration provider for the InertiaPsr15 module
 *
 * @see https://docs.laminas.dev/laminas-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies()
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'invokables' => [],
            'factories'  => [
                InertiaMiddleware::class => InertiaMiddlewareFactory::class,
                RootViewProviderInterface::class => RootViewProviderFactory::class,
                InertiaFactoryInterface::class => InertiaFactoryFactory::class
            ],
        ];
    }
}
