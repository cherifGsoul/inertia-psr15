<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Middleware;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function strtolower;
use function substr_count;

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
        $response->method('getHeaderLine')->with('Vary')->willReturn('');
        $response->method('withAddedHeader')->with('Vary', 'X-Inertia')->willReturn($response);
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
        $uri->method('getPath')->willReturn('/some-path');
        $uri->method('getQuery')->willReturn('');

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
        $response->method('withHeader')->with('X-Inertia-Location', '/some-path')->willReturn($response);
        $response->method('withoutHeader')->with('X-Inertia')->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($this->identicalTo($request))->willReturn($response);

        $middleware = new InertiaMiddleware($factory);
        $this->assertSame($response, $middleware->process($request, $handler));
    }

    public function testVersionMismatchNeverUsesAnUntrustedAuthorityOrUnsafePathInItsLocationHeader(): void
    {
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('current');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('//attacker.example/path');
        $uri->method('getQuery')->willReturn('next=1');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('withAttribute')->willReturn($request);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(true);
        $request->method('getHeaderLine')->with('X-Inertia-Version')->willReturn('stale');
        $request->method('getMethod')->willReturn('GET');
        $factory->method('fromRequest')->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withAddedHeader')->willReturn($response);
        $response->method('withStatus')->with(409)->willReturn($response);
        $response->expects($this->once())->method('withHeader')->with('X-Inertia-Location', '/attacker.example/path?next=1')->willReturn($response);
        $response->method('withoutHeader')->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        self::assertSame($response, (new InertiaMiddleware($factory))->process($request, $handler));
    }

    public function testVersionMismatchNormalizesBackslashNetworkPathsToAnInternalPath(): void
    {
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('current');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/\attacker.example/path');
        $uri->method('getQuery')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('withAttribute')->willReturn($request);
        $request->method('hasHeader')->with('X-Inertia')->willReturn(true);
        $request->method('getHeaderLine')->with('X-Inertia-Version')->willReturn('stale');
        $request->method('getMethod')->willReturn('GET');
        $factory->method('fromRequest')->willReturn($inertia);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withAddedHeader')->willReturn($response);
        $response->method('withStatus')->with(409)->willReturn($response);
        $response->expects($this->once())->method('withHeader')->with('X-Inertia-Location', '/attacker.example/path')->willReturn($response);
        $response->method('withoutHeader')->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        self::assertSame($response, (new InertiaMiddleware($factory))->process($request, $handler));
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

    public function testAddsOneVaryTokenToEveryResponseIncluding204AndRedirects(): void
    {
        $inertia = $this->createMock(InertiaInterface::class);
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $factory->method('fromRequest')->willReturn($inertia);
        $middleware = new InertiaMiddleware($factory);

        foreach ([
            new Response('php://memory', 204), new Response('php://memory', 302, [
                'Vary' => 'Accept, X-Inertia',
            ])] as $response) {
            $request = new ServerRequest();
            $handler = new class($response) implements RequestHandlerInterface {
                public function __construct(private readonly ResponseInterface $response) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->response;
                }
            };

            $result = $middleware->process($request, $handler);
            self::assertSame(1, substr_count(strtolower($result->getHeaderLine('Vary')), 'x-inertia'));
        }
    }

    public function testConvertsNormalInertiaRedirectWithFragmentExceptPrefetch(): void
    {
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('v1');
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $factory->method('fromRequest')->willReturn($inertia);
        $middleware = new InertiaMiddleware($factory);

        $request = new ServerRequest([], [], '/', 'GET', 'php://memory', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => 'v1',
        ]);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://memory', 302, [
                    'Location' => '/next#section',
                ]);
            }
        };
        $result = $middleware->process($request, $handler);
        self::assertSame(409, $result->getStatusCode());
        self::assertSame('/next#section', $result->getHeaderLine('X-Inertia-Redirect'));
        self::assertFalse($result->hasHeader('Location'));

        $prefetch = $request->withHeader('Purpose', 'prefetch');
        $result   = $middleware->process($prefetch, $handler);
        self::assertSame(302, $result->getStatusCode());
        self::assertSame('/next#section', $result->getHeaderLine('Location'));
    }

    public function testKeepsOrdinaryInertiaRedirectsAsRedirects(): void
    {
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('v1');
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $factory->method('fromRequest')->willReturn($inertia);
        $middleware = new InertiaMiddleware($factory);

        $request = new ServerRequest([], [], '/', 'GET', 'php://memory', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => 'v1',
        ]);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://memory', 302, [
                    'Location' => '/next',
                ]);
            }
        };
        $result = $middleware->process($request, $handler);

        self::assertSame(302, $result->getStatusCode());
        self::assertSame('/next', $result->getHeaderLine('Location'));
        self::assertFalse($result->hasHeader('X-Inertia-Redirect'));

        $put    = $request->withMethod('PUT');
        $result = $middleware->process($put, $handler);
        self::assertSame(303, $result->getStatusCode());
        self::assertFalse($result->hasHeader('X-Inertia-Redirect'));
    }

    public function testVersionMismatchHasOneVaryTokenAndIsAnExternalReloadResponse(): void
    {
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->method('getVersion')->willReturn('current');
        $factory = $this->createMock(InertiaFactoryInterface::class);
        $factory->method('fromRequest')->willReturn($inertia);
        $middleware = new InertiaMiddleware($factory);

        $request = new ServerRequest([], [], '/users?filter=active', 'GET', 'php://memory', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => 'stale',
        ]);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('/users?filter=active', $response->getHeaderLine('X-Inertia-Location'));
        self::assertSame('X-Inertia', $response->getHeaderLine('Vary'));
        self::assertFalse($response->hasHeader('X-Inertia'));
    }
}
