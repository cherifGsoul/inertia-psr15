<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Cherif\InertiaPsr15\Model\Page;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

class Inertia implements InertiaInterface
{
    private ServerRequestInterface $request;
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;
    private RootViewProviderInterface $rootViewProvider;
    private Page $page;

    public function __construct(
        ServerRequestInterface $request, 
        ResponseFactoryInterface $responseFactory, 
        StreamFactoryInterface $streamFactory,
        RootViewProviderInterface $rootViewProvider
    ) {
        $this->request = $request;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->rootViewProvider = $rootViewProvider;
        $this->page = Page::create();
    }

    public function render(string $component, array $props = []): ResponseInterface
    {
        $this->page = $this->page
                        ->withComponent($component)
                        ->withProps($props)
                        ->withUrl((string)$this->request->getUri());

        if ($this->request->hasHeader('X-Inertia-Partial-Data')) {
            $only = $this->request->getHeader('X-Inertia-Partial-Data');
            $props = ($only && $this->request->getHeaderLine('X-Inertia-Partial-Component'))
            ? array_intersect_key($props, array_flip((array) $only))
            : $props;
        }

        array_walk_recursive($props, function (&$prop) {
            if ($prop instanceof \Closure) {
                $prop = $prop();
            }
        });

        $this->page = $this->page->withProps($props);

        if ($this->request->hasHeader('X-Inertia')) {
            $json = json_encode($this->page);
            return $this->createResponse($json, 'application/json');
        }

        $rootViewProvider = $this->rootViewProvider;
        $html = $rootViewProvider($this->page);

        return $this->createResponse($html, 'text/html; charset=UTF-8');
    }

    public function version($version)
    {
        $this->page = $this->page->withVersion($version);
    }

    public function share(string $key, $value = null)
    {
        $this->page = $this->page->addProp($key, $value);
    }

    public function getVersion(): ?string
    {
        return $this->page->getVersion();
    }

    private function createResponse(string $data, string $contentType)
    {
        $stream = $this->streamFactory->createStream($data);
        return $this->responseFactory->createResponse()
                    ->withBody($stream)
                    ->withHeader('Content-Type', $contentType);
    }
}