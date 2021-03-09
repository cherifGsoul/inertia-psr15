<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Factory;

use Cherif\InertiaPsr15\Service\RootViewProviderDecorator;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;

class RootViewProviderFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $templateRenderer = $container->get(TemplateRendererInterface::class);
        return new RootViewProviderDecorator([$templateRenderer, 'render'], 'app.html.twig');
    }
}