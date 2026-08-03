<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Twig;

use JsonException;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Twig\InertiaExtension;

class InertiaExtensionTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testRendersTheV3InitialPageFormat(): void
    {
        $markup = (new InertiaExtension())->inertia(Page::from('Dashboard', [
            'title' => '<Dashboard>',
        ], '/'));

        $this->assertSame(
            '<script data-page="app" type="application/json">{"component":"Dashboard","props":{"title":"\u003CDashboard\u003E"},"url":"\/","version":null}</script><div id="app"></div>',
            (string) $markup
        );
    }
}
