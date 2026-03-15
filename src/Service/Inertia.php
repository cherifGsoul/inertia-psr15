<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Service;

use Cherif\InertiaPsr15\Model\LazyProp;
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

    public function render(string $component, array $props = [], ?string $url = null): ResponseInterface
    {
        $props = array_merge($this->page->getProps(), $props);
        $this->page = $this->page
            ->withComponent($component)
            ->withUrl($url ?? (string)$this->request->getUri());

        $only = [];
        if (
            $this->request->hasHeader('X-Inertia-Partial-Data')
            && $this->request->getHeaderLine('X-Inertia-Partial-Component') === $component
        ) {
            $only = explode(',', $this->request->getHeaderLine('X-Inertia-Partial-Data'));
        }

        $except = [];
        if (
            $this->request->hasHeader('X-Inertia-Partial-Except')
            && $this->request->getHeaderLine('X-Inertia-Partial-Component') === $component
        ) {
            $except = explode(',', $this->request->getHeaderLine('X-Inertia-Partial-Except'));
        }

        if ($only) {
            $props = array_intersect_key($props, array_flip((array) $only));
        }

        if ($except) {
            $props = array_diff_key($props, array_flip((array) $except));
        }

        if (empty($only) && empty($except)) {
            $props = array_filter($props, function ($prop) {
                return ! $prop instanceof LazyProp;
            });
        }

        // Handle Once props
        if ($this->request->hasHeader('X-Inertia-Reset')) {
            $reset = explode(',', $this->request->getHeaderLine('X-Inertia-Reset'));
            $props = array_diff_key($props, array_flip((array) $reset));
        }

        foreach ($props as $key => $prop) {
            if (is_callable($prop) || $prop instanceof LazyProp) {
                $props[$key] = $prop();
            }
        }

        array_walk_recursive($props, function (&$prop) {
            if (is_callable($prop) || $prop instanceof LazyProp ) {
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
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->page->getVersion();
    }

    public function defer(string $key, callable $callback, string $group = 'default'): self
    {
        $this->page = $this->page->withDeferredProps([$group => [$key]]);
        return $this->share($key, $callback);
    }

    public function merge(string $key, $value): self
    {
        $this->page = $this->page->withMergeProps([$key]);
        return $this->share($key, $value);
    }

    public function prepend(string $key, $value): self
    {
        $this->page = $this->page->withPrependProps([$key]);
        return $this->share($key, $value);
    }

    public function deepMerge(string $key, $value): self
    {
        $this->page = $this->page->withDeepMergeProps([$key]);
        return $this->share($key, $value);
    }

    public function once(string $key, $value): self
    {
        $this->page = $this->page->withOnceProps([$key => ['prop' => $key, 'expiresAt' => null]]);
        return $this->share($key, $value);
    }

    public function encryptHistory(bool $encryptHistory = true): self
    {
        $this->page = $this->page->withEncryptHistory($encryptHistory);
        return $this;
    }

    public function clearHistory(bool $clearHistory = true): self
    {
        $this->page = $this->page->withClearHistory($clearHistory);
        return $this;
    }

    private function createResponse(string $data, string $contentType)
    {
        $stream = $this->streamFactory->createStream($data);
        return $this->responseFactory->createResponse()
                    ->withBody($stream)
                    ->withHeader('Content-Type', $contentType);
    }

    public static function lazy(callable $callable): LazyProp
    {
        return new LazyProp($callable);
    }

    /**
     * @param string|ResponseInterface $destination
     * @param int $status
     * @return ResponseInterface
     */
    public function location($destination, int $status = 302): ResponseInterface
    {
        $response = $this->createResponse('', 'text/html; charset=UTF-8');

        // We check if InertiaMiddleware has set up the 'X-Inertia-Location' header, so we handle the response accordingly
        if ($this->request->hasHeader('X-Inertia')) {
            $response = $response->withStatus(409);
            return $response->withHeader(
                'X-Inertia-Location',
                $destination instanceof ResponseInterface ? $destination->getHeaderLine('Location') : $destination
            );
        }

        if ($destination instanceof ResponseInterface) {
            return $destination;
        }

        $response = $response->withStatus($status);
        return $response->withHeader('Location', $destination);
    }
}
