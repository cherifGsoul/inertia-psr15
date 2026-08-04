<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\View;

use Closure;
use Sirix\InertiaPsr15\Model\Page;

class RootViewProviderDecorator implements RootViewProviderInterface
{
    /** @var Closure(string, array{page: Page}): string */
    private readonly Closure $decorated;

    /**
     * @param callable(string, array{page: Page}): string $decorated
     */
    public function __construct(callable $decorated, private readonly string $rootView)
    {
        $this->decorated = $decorated(...);
    }

    public function __invoke(Page $page): string
    {
        return $this->render($page);
    }

    public function render(Page $page): string
    {
        $decorated = $this->decorated;

        return $decorated($this->rootView, [
            'page' => $page,
        ]);
    }
}
