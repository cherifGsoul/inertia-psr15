<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Factory;

use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\ConfigProvider;
use Sirix\InertiaPsr15\Factory\InertiaExtensionFactory;
use Sirix\InertiaPsr15\Factory\InertiaFactoryFactory;
use Sirix\InertiaPsr15\Factory\InertiaMiddlewareFactory;
use Sirix\InertiaPsr15\Factory\RootViewProviderFactory;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Twig\InertiaExtension;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;

class ConfigProviderTest extends TestCase
{
    public function testRegistersAllContainerFactoriesFromTheFactoryNamespace(): void
    {
        $config = (new ConfigProvider())();

        self::assertSame([
            InertiaMiddleware::class         => InertiaMiddlewareFactory::class,
            RootViewProviderInterface::class => RootViewProviderFactory::class,
            InertiaFactoryInterface::class   => InertiaFactoryFactory::class,
            InertiaExtension::class          => InertiaExtensionFactory::class,
        ], $config['dependencies']['factories']);
    }

    public function testProvidesTheDefaultRootViewConfiguration(): void
    {
        $config = (new ConfigProvider())();

        self::assertSame([
            'root_view' => 'app.html.twig',
        ], $config['inertia_psr15']);
    }
}
