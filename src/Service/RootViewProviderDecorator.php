<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Cherif\InertiaPsr15\Model\Page;


class RootViewProviderDecorator implements RootViewProviderInterface
{
    private $decorated;
    private string $rootView;

    public function __construct(callable $decorated, string $rootView)
    {
        $this->decorated = $decorated;
        $this->rootView = $rootView;
    }

    public function __invoke(Page $page): string
    {
        return $this->render($page);
    }

    public function render(Page $page): string
    {
        $decorated = $this->decorated;
        return $decorated($this->rootView, ['page' => $page]);
    }
}