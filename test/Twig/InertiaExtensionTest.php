<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Twig;

use JsonException;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Twig\InertiaExtension;

use function substr_count;

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

    public function testEscapesScriptBreakoutPayloadsInTheInitialPageJson(): void
    {
        $markup = (string) (new InertiaExtension())->inertia(Page::from('Dashboard', [
            'payload' => '</script><img src=x onerror=alert(1)>',
        ], '/'));

        self::assertStringNotContainsString('</script><img', $markup);
        self::assertStringContainsString('\u003C\/script\u003E', $markup);
        self::assertSame(1, substr_count($markup, '</script>'));
    }
}
