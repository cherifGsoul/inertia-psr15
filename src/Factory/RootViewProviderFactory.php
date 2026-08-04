<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Factory;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\InertiaPsr15\View\RootViewProviderDecorator;

class RootViewProviderFactory
{
    /**
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve a service
     * @throws InvalidConfigValueException      when the root-view configuration is invalid
     * @throws InvalidContainerServiceException when a resolved service has an unexpected type
     * @throws MissingContainerServiceException when the template renderer is not registered
     */
    public function __invoke(ContainerInterface $container): RootViewProviderDecorator
    {
        $resolver         = ContainerResolver::forFactory($container, self::class);
        $templateRenderer = $resolver->get(TemplateRendererInterface::class);
        $rootView         = ConfigReader::fromContainer($resolver)->nonEmptyString('inertia_psr15.root_view', 'app.html.twig');

        $callback = (static fn (string $template, array $params): string => $templateRenderer->render($template, $params));

        return new RootViewProviderDecorator($callback, $rootView);
    }
}
