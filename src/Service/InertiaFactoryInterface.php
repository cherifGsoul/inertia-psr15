<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Psr\Http\Message\ServerRequestInterface as Request;

interface InertiaFactoryInterface
{
    /**
     * @param Request $request
     * @return InertiaInterface
     */
    public function fromRequest(Request $request): InertiaInterface;
}