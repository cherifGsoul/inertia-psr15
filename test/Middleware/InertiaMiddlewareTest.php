<?php

namespace InertiaPsr15Test\Middleware;

use Cherif\InertiaPsr15\Middleware\InertiaMiddleware;
use Cherif\InertiaPsr15\Service\InertiaFactoryInterface;
use Cherif\InertiaPsr15\Service\InertiaInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InertiaMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testProcessWithoutInertiaHeader()
    {
        $request = $this->prophesize(ServerRequestInterface::class);

        $request->hasHeader('X-Inertia')->willReturn(false);

        $factory = $this->prophesize(InertiaFactoryInterface::class);
        $inertia = $this->prophesize(InertiaInterface::class);
        
        $request->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia)->willReturn($request);

        $factory->fromRequest(Argument::that([$request, 'reveal']))->willReturn($inertia);
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->willReturn($response);

        $middleware = new InertiaMiddleware($factory->reveal());
        $this->assertSame($response, $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * 
     */
    public function testDoesntChangeHandlerResponseForTheSameVersion()
    {
        $factory = $this->prophesize(InertiaFactoryInterface::class);
        $inertia = $this->prophesize(InertiaInterface::class);
        $inertia->getVersion()->willReturn('12345');
        
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, Argument::that([$inertia, 'reveal']))->willReturn($request);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->getHeader('X-Inertia-Version')->willReturn('12345');
        $request->getMethod()->willReturn('GET');
        
        $factory->fromRequest($request)->willReturn($inertia);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(202);
       
        $response->withAddedHeader('Vary', 'Accept')->willReturn($response);
        $response->withAddedHeader('X-Inertia', 'true')->willReturn($response);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->willReturn($response);

        $middleware = new InertiaMiddleware($factory->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    public function testAddsInertiaLocationToResponseWhenVersionChanges()
    {
        $factory = $this->prophesize(InertiaFactoryInterface::class);
        $inertia = $this->prophesize(InertiaInterface::class);
        $inertia->getVersion()->willReturn('forbarbaz');

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/some-path');
        
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia->reveal())->willReturn($request);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->getHeader('X-Inertia-Version')->willReturn('12345');
        $request->getMethod()->willReturn('GET');
        
        $factory->fromRequest($request)->willReturn($inertia);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(202);
       
        $response->withAddedHeader('Vary', 'Accept')->willReturn($response);
        $response->withAddedHeader('X-Inertia', 'true')->willReturn($response);
        $response->withAddedHeader('X-Inertia-Location', $uri->reveal()->getPath())->willReturn($response);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->willReturn($response);

        $middleware = new InertiaMiddleware($factory->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    public function testItChangesResponseCodeTo303WhenRedirectHappensForPutPatchDelete()
    {
        $factory = $this->prophesize(InertiaFactoryInterface::class);
        $inertia = $this->prophesize(InertiaInterface::class);
        $inertia->getVersion()->willReturn('12345');
        
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia->reveal())->willReturn($request);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->getHeader('X-Inertia-Version')->willReturn('12345');
        $request->getMethod()->willReturn('PUT');
        
        $factory->fromRequest($request)->willReturn($inertia);

        $response = $this->prophesize(ResponseInterface::class);
       
        $response->withAddedHeader('Vary', 'Accept')->willReturn($response);
        $response->withAddedHeader('X-Inertia', 'true')->willReturn($response);
        $response->getStatusCode()->willReturn(302);
        $response->withStatus(303)->shouldBeCalled();
        $response->withStatus(303)->willReturn($response);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->willReturn($response);

        $middleware = new InertiaMiddleware($factory->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }
}