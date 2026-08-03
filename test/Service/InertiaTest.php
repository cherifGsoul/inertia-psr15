<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Service;

use JsonException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Sirix\InertiaPsr15\Service\Inertia;
use Sirix\InertiaPsr15\Service\RootViewProviderInterface;

class InertiaTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testRenderReturnPsr7ResponseWithJsonWhenInertiaHeaderIsPresent(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', false],
        ]);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('/');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $expectedJson    = '{"component":"component","props":[],"url":"\/","version":null}';
        $capturedPayload = null;

        $streamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with($this->callback(function(string $payload) use (&$capturedPayload, $expectedJson) {
                $capturedPayload = $payload;

                return $payload === $expectedJson;
            }))
            ->willReturn($stream)
        ;

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response
            ->expects($this->once())
            ->method('withBody')
            ->with($stream)
            ->willReturn($response)
        ;

        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response)
        ;

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->render('component');

        $this->validateResponseInstance($returnedResponse);
        $this->assertSame($expectedJson, $capturedPayload);
    }

    /**
     * @throws JsonException
     */
    public function testRenderReturnPsr7ResponseWithHtmlWhenInertiaHeaderIsNotPresent(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', false],
            ['X-Inertia-Partial-Data', false],
        ]);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('/');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $expectedHtml    = '<html>ok</html>';
        $capturedPayload = null;

        $streamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with($this->callback(function(string $payload) use (&$capturedPayload, $expectedHtml) {
                $capturedPayload = $payload;

                return $payload === $expectedHtml;
            }))
            ->willReturn($stream)
        ;

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);
        $rootViewProvider->method('__invoke')->willReturn($expectedHtml);

        $response
            ->expects($this->once())
            ->method('withBody')
            ->with($stream)
            ->willReturn($response)
        ;

        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'text/html; charset=UTF-8')
            ->willReturn($response)
        ;

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->render('component');

        $this->validateResponseInstance($returnedResponse);
        $this->assertSame($expectedHtml, $capturedPayload);
    }

    /**
     * @throws JsonException
     */
    public function testRenderReturnPartialDataWhenHeaderContainsPartialData(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', true],
            ['X-Inertia-Partial-Except', false],
        ]);
        $request->method('getHeaderLine')->willReturnMap([
            ['X-Inertia-Partial-Component', 'component'],
            ['X-Inertia-Partial-Data', 'key2'],
        ]);
        $json         = '{"component":"component","props":{"key2":"value2"},"url":"callback()","version":null}';
        $jsonResponse = null;

        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('callback()');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => fn () => 'value1',
                'key2' => fn () => 'value2',
            ]
        );

        $this->validateResponseInstance($returnedResponse);
        $this->assertSame($json, $jsonResponse);
    }

    /**
     * @throws JsonException
     */
    public function testRenderReturnResponseWithRequestedUrl(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', false],
        ]);
        $invalidJson  = '{"component":"component","props":{"key1":"value1","key2":"value2"},"url":"callback()","version":null}';
        $validJson    = '{"component":"component","props":{"key1":"value1","key2":"value2"},"url":"\/test\/url","version":null}';
        $jsonResponse = null;

        $uri = $this->createMock(UriInterface::class);
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
            '/test/url'
        );

        $this->validateResponseInstance($returnedResponse);
        $this->assertNotSame($invalidJson, $jsonResponse);
        $this->assertSame($validJson, $jsonResponse);
    }

    /**
     * @throws JsonException
     */
    public function testRenderReturnResponseWithoutOptionalProps(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', false],
        ]);
        $invalidJson  = '{"component":"component","props":{"key1":"value1","key2":"value2","auth":{"notifications":["New message"],"user":"Jane"}},"url":"callback()","version":null}';
        $validJson    = '{"component":"component","props":{"key2":"value2","auth":{"user":"Jane"}},"url":"callback()","version":null}';
        $jsonResponse = null;

        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('callback()');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => Inertia::optional(fn () => 'value1'),
                'key2' => fn () => 'value2',
                'auth' => fn () => [
                    'notifications' => Inertia::optional(fn () => ['New message']),
                    'user'          => fn () => 'Jane',
                ],
            ]
        );

        $this->validateResponseInstance($returnedResponse);
        $this->assertNotSame($invalidJson, $jsonResponse);
        $this->assertSame($validJson, $jsonResponse);
    }

    /**
     * @throws JsonException
     */
    public function testRenderReturnResponseWithRequestedOptionalProps(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', true],
            ['X-Inertia-Partial-Except', false],
        ]);
        $request->method('getHeaderLine')->willReturnMap([
            ['X-Inertia-Partial-Component', 'component'],
            ['X-Inertia-Partial-Data', 'key1'],
        ]);
        $invalidJson  = '{"component":"component","props":{"key2":"value2"},"url":"callback()","version":null}';
        $validJson    = '{"component":"component","props":{"key1":"value1"},"url":"callback()","version":null}';
        $jsonResponse = null;

        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('callback()');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => Inertia::optional(fn () => 'value1'),
                'key2' => fn () => 'value2',
            ]
        );

        $this->validateResponseInstance($returnedResponse);
        $this->assertNotSame($invalidJson, $jsonResponse);
        $this->assertSame($validJson, $jsonResponse);
    }

    /**
     * @throws JsonException
     */
    public function testRenderReturnsRequestedNestedPartialProps(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', true],
            ['X-Inertia-Partial-Except', false],
        ]);
        $request->method('getHeaderLine')->willReturnMap([
            ['X-Inertia-Partial-Component', 'component'],
            ['X-Inertia-Partial-Data', 'auth.notifications'],
        ]);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('callback()');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);
        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $jsonResponse  = null;
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });
        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);
        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia($request, $responseFactory, $streamFactory, $rootViewProvider);
        $inertia->render('component', [
            'auth'     => fn () => [
                'user'          => fn () => 'Jane',
                'notifications' => Inertia::optional(fn () => ['New message']),
            ],
            'settings' => fn () => [
                'theme' => 'dark',
            ],
        ]);

        $this->assertSame(
            '{"component":"component","props":{"auth":{"notifications":["New message"]}},"url":"callback()","version":null}',
            $jsonResponse
        );
    }

    /**
     * @throws JsonException
     */
    public function testPartialExceptTakesPrecedenceAndDoesNotResolveOptionalProps(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', true],
            ['X-Inertia-Partial-Except', true],
        ]);
        $request->method('getHeaderLine')->willReturnMap([
            ['X-Inertia-Partial-Component', 'component'],
            ['X-Inertia-Partial-Data', 'auth.notifications'],
            ['X-Inertia-Partial-Except', 'auth.notifications'],
        ]);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('callback()');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);
        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $jsonResponse  = null;
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });
        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);
        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia($request, $responseFactory, $streamFactory, $rootViewProvider);
        $inertia->render('component', [
            'auth'     => fn () => [
                'user'          => fn () => 'Jane',
                'notifications' => Inertia::optional(fn () => ['New message']),
            ],
            'settings' => fn () => [
                'theme' => 'dark',
            ],
        ]);

        $this->assertSame(
            '{"component":"component","props":{"auth":{"user":"Jane"},"settings":{"theme":"dark"}},"url":"callback()","version":null}',
            $jsonResponse
        );
    }

    /**
     * @throws JsonException
     */
    public function testPartialReloadTreatsDottedTopLevelKeyAsLiteral(): void
    {
        $json = $this->renderPartialReload('feature.flag', [
            'feature.flag' => true,
            'feature'      => [
                'flag' => false,
            ],
        ]);

        $this->assertSame(
            '{"component":"component","props":{"feature.flag":true},"url":"callback()","version":null}',
            $json
        );
    }

    /**
     * @throws JsonException
     */
    public function testPartialExceptTreatsDottedTopLevelKeyAsLiteral(): void
    {
        $json = $this->renderPartialReload('feature.flag', [
            'feature.flag' => true,
            'feature'      => [
                'flag' => false,
            ],
            'keep'         => true,
        ], 'feature.flag');

        $this->assertSame(
            '{"component":"component","props":{"feature":{"flag":false},"keep":true},"url":"callback()","version":null}',
            $json
        );
    }

    /**
     * @throws JsonException
     */
    public function testOverlappingPartialPathsAreOrderIndependent(): void
    {
        foreach (['auth,auth.user', 'auth.user,auth'] as $only) {
            $json = $this->renderPartialReload($only, [
                'auth' => [
                    'user' => 'Jane',
                    'role' => 'admin',
                ],
            ]);

            $this->assertSame(
                '{"component":"component","props":{"auth":{"user":"Jane","role":"admin"}},"url":"callback()","version":null}',
                $json
            );
        }
    }

    /**
     * @throws JsonException
     */
    public function testPartialReloadResolvesSharedClosureContainerOnce(): void
    {
        $authCalls = 0;

        $json = $this->renderPartialReload('auth.user,auth.notifications', [
            'auth' => function() use (&$authCalls): array {
                ++$authCalls;

                return [
                    'user'          => 'Jane',
                    'notifications' => ['New message'],
                    'role'          => 'admin',
                ];
            },
        ]);

        $this->assertSame(1, $authCalls);
        $this->assertSame(
            '{"component":"component","props":{"auth":{"user":"Jane","notifications":["New message"]}},"url":"callback()","version":null}',
            $json
        );
    }

    public function testLocationReturnResponseWithLocationAsStringWithNotExistingInertiaHeader(): void
    {
        $request      = $this->createMock(ServerRequestInterface::class);
        $htmlResponse = null;

        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', false],
        ]);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(fn (string $data) => $stream);

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);
        $response->method('withStatus')->willReturn($response);

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->location('new-location');

        $this->validateResponseInstance($returnedResponse);
        $this->assertNotSame('', $htmlResponse);
    }

    public function testLocationReturnResponseWithLocationAsStringWithExistingInertiaHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
        ]);
        $htmlResponse = null;

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(fn (string $data) => $stream);

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);
        $response->method('withStatus')->willReturn($response);

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->location('new-location');

        $this->validateResponseInstance($returnedResponse);
        $this->assertNotSame('', $htmlResponse);
    }

    public function testLocationReturnResponseWithLocationAsResponseInterfaceWithExistingInertiaHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
        ]);
        $htmlResponse = null;

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturnCallback(fn (string $data) => $stream);

        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);

        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);
        $response->method('withStatus')->willReturn($response);

        $locationResponse = $this->createMock(ResponseInterface::class);
        $locationResponse->method('getHeaderLine')->with('Location')->willReturn('new-location');

        $inertia = new Inertia(
            $request,
            $responseFactory,
            $streamFactory,
            $rootViewProvider
        );

        $returnedResponse = $inertia->location($locationResponse);

        $this->validateResponseInstance($returnedResponse);
        $this->assertNotSame('', $htmlResponse);
    }

    public function validateResponseInstance(?ResponseInterface $returnedResponse): void
    {
        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @throws JsonException
     */
    private function renderPartialReload(string $only, array $props, ?string $except = null): string
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('hasHeader')->willReturnMap([
            ['X-Inertia', true],
            ['X-Inertia-Partial-Data', true],
            ['X-Inertia-Partial-Except', null !== $except],
        ]);

        $headerLines = [
            ['X-Inertia-Partial-Component', 'component'],
            ['X-Inertia-Partial-Data', $only],
        ];

        if (null !== $except) {
            $headerLines[] = ['X-Inertia-Partial-Except', $except];
        }

        $request->method('getHeaderLine')->willReturnMap($headerLines);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('callback()');
        $request->method('getUri')->willReturn($uri);

        $response        = $this->createMock(ResponseInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);
        $stream        = $this->createMock(StreamInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $jsonResponse  = null;
        $streamFactory->method('createStream')->willReturnCallback(function(string $data) use (&$jsonResponse, $stream) {
            $jsonResponse = $data;

            return $stream;
        });
        $rootViewProvider = $this->createMock(RootViewProviderInterface::class);
        $response->method('withBody')->willReturn($response);
        $response->method('withHeader')->willReturn($response);

        $inertia = new Inertia($request, $responseFactory, $streamFactory, $rootViewProvider);
        $inertia->render('component', $props);

        return (string) $jsonResponse;
    }
}
