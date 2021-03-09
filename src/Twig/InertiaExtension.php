<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

class InertiaExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [new TwigFunction('inertia', [$this, 'inertia'])];
    }

    public function inertia($page)
    {
        return new Markup('<div id="app" data-page="'.htmlspecialchars(json_encode($page)).'"></div>', 'UTF-8');
    }
}