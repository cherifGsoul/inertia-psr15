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

class InertiaMiddlewareVersionReproTest extends TestCase
{
    use ProphecyTrait;

    public function testDoesntConflictWhenVersionIsMissingAndNull()
    {
        $factory = $this->prophesize(InertiaFactoryInterface::class);
        $inertia = $this->prophesize(InertiaInterface::class);
        $inertia->getVersion()->willReturn(null);
        
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, Argument::any())->willReturn($request);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->getHeaderLine('X-Inertia-Version')->willReturn(''); // missing header returns empty string
        $request->getMethod()->willReturn('GET');
        
        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());
        
        $factory->fromRequest($request)->willReturn($inertia);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
       
        $response->withAddedHeader('Vary', 'X-Inertia')->willReturn($response);
        $response->withAddedHeader('X-Inertia', 'true')->willReturn($response);

        // We EXPECT it NOT to call withStatus(409)
        $response->withStatus(409)->shouldNotBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn($response);

        $middleware = new InertiaMiddleware($factory->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }
}
