<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Cherif\InertiaPsr15\Model\Page;

Interface RootViewProviderInterface
{
    public function __invoke(Page $page): String;
}