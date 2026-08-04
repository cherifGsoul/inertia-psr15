<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Service;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamFactoryInterface;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;

class InertiaFactory implements InertiaFactoryInterface
{
    private readonly RootViewProviderInterface $rootViewProvider;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        RootViewProviderInterface $rootViewProvider
    ) {
        $this->rootViewProvider = $rootViewProvider;
    }

    public function fromRequest(Request $request): InertiaInterface
    {
        return new Inertia(
            $request,
            $this->responseFactory,
            $this->streamFactory,
            $this->rootViewProvider
        );
    }
}
