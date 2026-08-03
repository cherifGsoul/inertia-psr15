<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Twig;

use JsonException;
use Sirix\InertiaPsr15\Model\Page;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

use function json_encode;

class InertiaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('inertia', $this->inertia(...))];
    }

    /**
     * @throws JsonException
     */
    public function inertia(Page $page): Markup
    {
        return new Markup(
            '<script data-page="app" type="application/json">'
            . json_encode(
                $page,
                JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_THROW_ON_ERROR
            )
            . '</script><div id="app"></div>',
            'UTF-8'
        );
    }
}
