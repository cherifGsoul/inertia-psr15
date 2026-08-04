<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Factory;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\InertiaPsr15\View\RootViewProviderDecorator;

use function is_array;
use function is_string;

class RootViewProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RootViewProviderDecorator
    {
        $templateRenderer = $container->get(TemplateRendererInterface::class);
        $config           = $container->has('config') ? $container->get('config') : [];

        $rootView = 'app.html.twig';
        if (is_array($config) && isset($config['inertia_psr15']['root_view']) && is_string($config['inertia_psr15']['root_view'])) {
            $rootView = $config['inertia_psr15']['root_view'];
        }

        $callback = (static fn (string $template, array $params): string => $templateRenderer->render($template, $params));

        return new RootViewProviderDecorator($callback, $rootView);
    }
}
