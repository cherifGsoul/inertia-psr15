<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;

class InertiaMiddlewareTest extends TestCase
{
    public function testProcessWithoutInertiaHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(false);
        $inertia = $this->createMock(InertiaInterface::class);
        $request->method('withAttribute')->with(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia)->willReturn($request);

        $factory = $this->createMock(InertiaFactoryInterface::class);
        $factory->method('fromRequest')->with($this->identicalTo($request))->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($this->identicalTo($request))->willReturn($response);

        $middleware = new InertiaMiddleware($factory);
        $this->assertSame($response, $middleware->process($request, $handler));
    }

    public function testDoesntChangeHandlerResponseForTheSameVersion(): void
    {
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('12345');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('withAttribute')->with(InertiaMiddleware::INERTIA_ATTRIBUTE, $this->identicalTo($inertia))->willReturn($request);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(true);
        $request->method('getHeaderLine')->with('X-Inertia-Version')->willReturn('12345');
        $request->method('getMethod')->willReturn('GET');

        $factory->method('fromRequest')->with($this->identicalTo($request))->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);
        $response->method('withAddedHeader')->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($this->identicalTo($request))->willReturn($response);

        $middleware = new InertiaMiddleware($factory);
        $this->assertSame($response, $middleware->process($request, $handler));
    }

    public function testAddsInertiaLocationToResponseWhenVersionChanges(): void
    {
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('forbarbaz');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('http://example.com/some-path');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('withAttribute')->with(InertiaMiddleware::INERTIA_ATTRIBUTE, $this->identicalTo($inertia))->willReturn($request);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(true);
        $request->method('getHeaderLine')->with('X-Inertia-Version')->willReturn('12345');
        $request->method('getMethod')->willReturn('GET');

        $factory->method('fromRequest')->with($this->identicalTo($request))->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);
        $response->method('withAddedHeader')->willReturn($response);
        $response->method('withStatus')->with(409)->willReturn($response);
        $response->method('withHeader')->with('X-Inertia-Location', 'http://example.com/some-path')->willReturn($response);
        $response->method('withoutHeader')->with('X-Inertia')->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($this->identicalTo($request))->willReturn($response);

        $middleware = new InertiaMiddleware($factory);
        $this->assertSame($response, $middleware->process($request, $handler));
    }

    public function testItChangesResponseCodeTo303WhenRedirectHappensForPutPatchDelete(): void
    {
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('12345');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('withAttribute')->with(InertiaMiddleware::INERTIA_ATTRIBUTE, $this->identicalTo($inertia))->willReturn($request);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(true);
        $request->method('getHeaderLine')->with('X-Inertia-Version')->willReturn('12345');
        $request->method('getMethod')->willReturn('PUT');

        $factory->method('fromRequest')->with($this->identicalTo($request))->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withAddedHeader')->willReturn($response);
        $response->method('getStatusCode')->willReturn(302);
        $response->expects($this->once())->method('withStatus')->with(303)->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($this->identicalTo($request))->willReturn($response);

        $middleware = new InertiaMiddleware($factory);
        $this->assertSame($response, $middleware->process($request, $handler));
    }

    public function testItRemovesInertiaHeaderForExternalRedirects(): void
    {
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('12345');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('withAttribute')->with(InertiaMiddleware::INERTIA_ATTRIBUTE, $this->identicalTo($inertia))->willReturn($request);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(true);
        $request->method('getHeaderLine')->with('X-Inertia-Version')->willReturn('12345');
        $request->method('getMethod')->willReturn('POST');

        $factory->method('fromRequest')->with($this->identicalTo($request))->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withAddedHeader')->willReturn($response);
        $response->method('hasHeader')->with('X-Inertia-Location')->willReturn(true);
        $response->method('getStatusCode')->willReturn(409);
        $response->expects($this->once())->method('withoutHeader')->with('X-Inertia')->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($this->identicalTo($request))->willReturn($response);

        $middleware = new InertiaMiddleware($factory);
        $this->assertSame($response, $middleware->process($request, $handler));
    }
}
