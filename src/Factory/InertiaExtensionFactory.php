<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Factory;

use Psr\Container\ContainerInterface;
use Sirix\InertiaPsr15\Twig\InertiaExtension;

class InertiaExtensionFactory
{
    public function __invoke(ContainerInterface $container): InertiaExtension
    {
        return new InertiaExtension();
    }
}
