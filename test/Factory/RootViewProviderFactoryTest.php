<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Factory;

use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sirix\InertiaPsr15\Factory\RootViewProviderFactory;
use Sirix\InertiaPsr15\Model\Page;

class RootViewProviderFactoryTest extends TestCase
{
    public function testUsesTheConfiguredRootView(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer
            ->expects(self::once())
            ->method('render')
            ->with('inertia/app.html.twig', [
                'page' => Page::from('Dashboard'),
            ])
            ->willReturn('<html></html>')
        ;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('config')->willReturn(true);
        $container->method('get')->willReturnMap([
            [TemplateRendererInterface::class, $renderer],
            [
                'config', [
                    'inertia_psr15' => [
                        'root_view' => 'inertia/app.html.twig',
                    ],
                ]],
        ]);

        $provider = (new RootViewProviderFactory())($container);

        self::assertSame('<html></html>', $provider(Page::from('Dashboard')));
    }
}
