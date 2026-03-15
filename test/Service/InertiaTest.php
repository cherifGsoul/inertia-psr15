<?php

namespace InertiaPsr15Test\Service;

use Cherif\InertiaPsr15\Model\Page;
use Cherif\InertiaPsr15\Service\Inertia;
use Cherif\InertiaPsr15\Service\RootViewProviderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class InertiaTest extends TestCase
{
    use ProphecyTrait;

    public function testRenderReturnPsr7ResponseWithJsonWhenInertiaHeaderIsPresent()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());
        
        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->willReturn($stream);

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(), 
            $responseFactory->reveal(), 
            $streamFactory->reveal(), 
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render('component');

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertNotSame($response, $returnedResponse);
    }


    public function testRenderReturnPsr7ResponseWithHtmlWhenInertiaHeaderIsNotPresent()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());
        
        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->willReturn($stream);

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);
        $rootViewProvider->__invoke(Argument::type(Page::class))->willReturn('html');

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->willReturn($response);
        
        $inertia = new Inertia(
            $request->reveal(), 
            $responseFactory->reveal(), 
            $streamFactory->reveal(), 
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render('component');

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertNotSame($response, $returnedResponse);
    }


    public function testRenderReturnPartialDataWhenHeaderContainsPartialData()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);
        $request->getHeaderLine('X-Inertia-Partial-Component')->willReturn('component');
        $request->getHeaderLine('X-Inertia-Partial-Data')->willReturn('key2');
        $json = '{"component":"component","props":{"key2":"value2"},"url":"http:\/\/example.com","version":null,"encryptHistory":false,"clearHistory":false}';
        $jsonResponse = null;

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function ($args) use (&$jsonResponse, $stream){
            $jsonResponse = $args[0];
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => fn() => 'value1',
                'key2' => fn() => 'value2'
            ]
        );

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertSame($json, $jsonResponse);
    }

    public function testRenderReturnResponseWithRequestedUrl()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);
        $invalidJson = '{"component":"component","props":{"key1":"value1","key2":"value2"},"url":"http:\/\/example.com","version":null,"encryptHistory":false,"clearHistory":false}';
        $validJson = '{"component":"component","props":{"key1":"value1","key2":"value2"},"url":"\/test\/url","version":null,"encryptHistory":false,"clearHistory":false}';
        $jsonResponse = null;

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function ($args) use (&$jsonResponse, $stream){
            $jsonResponse = $args[0];
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => 'value1',
                'key2' => 'value2'
            ]
            ,'/test/url'
        );

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertNotSame($invalidJson, $jsonResponse);
        $this->assertSame($validJson, $jsonResponse);
    }

    public function testRenderReturnResponseWithoutLazyProps()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);
        $validJson = '{"component":"component","props":{"key2":"value2"},"url":"http:\/\/example.com","version":null,"encryptHistory":false,"clearHistory":false}';
        $jsonResponse = null;

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function ($args) use (&$jsonResponse, $stream){
            $jsonResponse = $args[0];
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => Inertia::lazy(fn() => 'value1'),
                'key2' => fn() => 'value2'
            ]
        );

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertSame($validJson, $jsonResponse);
    }

    public function testRenderReturnResponseWithLazyProps()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);
        $request->getHeaderLine('X-Inertia-Partial-Component')->willReturn('component');
        $request->getHeaderLine('X-Inertia-Partial-Data')->willReturn('key1');
        $validJson = '{"component":"component","props":{"key1":"value1"},"url":"http:\/\/example.com","version":null,"encryptHistory":false,"clearHistory":false}';
        $jsonResponse = null;

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function ($args) use (&$jsonResponse, $stream){
            $jsonResponse = $args[0];
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(
            'component',
            [
                'key1' => Inertia::lazy(fn() => 'value1'),
                'key2' => fn() => 'value2'
            ]
        );

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertSame($validJson, $jsonResponse);
    }

    public function testRenderFiltersLazyPropsWhenPartialComponentMismatch()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);
        $request->getHeaderLine('X-Inertia-Partial-Component')->willReturn('other-component');
        $request->getHeaderLine('X-Inertia-Partial-Data')->willReturn('key1');
        
        // key1 is LazyProp, should be filtered out because component mismatch
        // key2 is not LazyProp, should be kept
        $validJson = '{"component":"component","props":{"key2":"value2"},"url":"http:\/\/example.com","version":null,"encryptHistory":false,"clearHistory":false}';
        $jsonResponse = null;

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function ($args) use (&$jsonResponse, $stream){
            $jsonResponse = $args[0];
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $inertia->render(
            'component',
            [
                'key1' => Inertia::lazy(fn() => 'value1'),
                'key2' => 'value2'
            ]
        );

        $this->assertSame($validJson, $jsonResponse);
    }

    public function testLocationReturnResponseWithLocationAsStringWithNotExistingInertiaHeader()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(false);
        $htmlResponse = null;

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function () use ($stream){
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->willReturn($response);
        $response->withStatus(302)->willReturn($response);
        $response->withHeader('Location', 'new-location')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );


        $returnedResponse = $inertia->location('new-location');

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
    }

    public function testLocationReturnResponseWithLocationAsStringWithExistingInertiaHeader()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $htmlResponse = null;

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function () use ($stream){
            return $stream->reveal();
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->willReturn($response);
        $response->withStatus(409)->willReturn($response);
        $response->withHeader('X-Inertia-Location', 'new-location')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->location('new-location');

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
    }

    public function testLocationReturnResponseWithLocationAsResponseInterfaceWithExistingInertiaHeader()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $htmlResponse = null;

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function () use ($stream){
            return $stream;
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('X-Inertia', true)->willReturn($response);
        $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->willReturn($response);

        $locationResponse = $this->prophesize(ResponseInterface::class);
        $locationResponse->getHeaderLine('Location')->willReturn('new-location');

        $response->withStatus(409)->willReturn($response);
        $response->withHeader('X-Inertia-Location', $locationResponse instanceof ResponseInterface ? $locationResponse->getHeaderLine('Location') : $locationResponse)->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->location($locationResponse);

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertNotSame('', $htmlResponse);
    }

}
