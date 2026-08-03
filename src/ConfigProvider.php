<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15;

use Sirix\InertiaPsr15\Factory\InertiaFactoryFactory;
use Sirix\InertiaPsr15\Factory\RootViewProviderFactory;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Middleware\InertiaMiddlewareFactory;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\RootViewProviderInterface;
use Sirix\InertiaPsr15\Twig\InertiaExtension;
use Sirix\InertiaPsr15\Twig\InertiaExtensionFactory;

/**
 * The configuration provider for the InertiaPsr15 module.
 *
 * @see https://docs.laminas.dev/laminas-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array.
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array{dependencies: array{invokables: array<class-string, class-string>|array<empty, empty>, factories: array<class-string, class-string>}}
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * Returns the container dependencies.
     *
     * @return array{invokables: array<class-string, class-string>|array<empty, empty>, factories: array<class-string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'invokables' => [],
            'factories'  => [
                InertiaMiddleware::class         => InertiaMiddlewareFactory::class,
                RootViewProviderInterface::class => RootViewProviderFactory::class,
                InertiaFactoryInterface::class   => InertiaFactoryFactory::class,
                InertiaExtension::class          => InertiaExtensionFactory::class,
            ],
        ];
    }
}
