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

        $uri = $this->prophesize(UriInterface::class);
        $request->getUri()->willReturn(Argument::that([$uri, 'reveal']));
        
        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->willReturn($stream);

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('X-Inertia', true)->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(), 
            $responseFactory->reveal(), 
            $streamFactory->reveal(), 
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(Argument::type('string'));

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertNotSame($response, $returnedResponse);
    }


    public function testRenderReturnPsr7ResponseWithHtmlWhenInertiaHeaderIsNotPresent()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $request->getUri()->willReturn(Argument::that([$uri, 'reveal']));
        
        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->willReturn($stream);

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);
        $rootViewProvider->__invoke(Argument::type(Page::class))->willReturn(Argument::type('string'));

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->willReturn($response);
        
        $inertia = new Inertia(
            $request->reveal(), 
            $responseFactory->reveal(), 
            $streamFactory->reveal(), 
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(Argument::type('string'));

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertNotSame($response, $returnedResponse);
    }


    public function testRenderReturnPartialDataWhenHeaderContainsPartialData()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(true);
        $request->getHeaderLine('X-Inertia-Partial-Component')->willReturn(Argument::type('string'));
        $request->getHeader('X-Inertia-Partial-Data')->willReturn([
            'key2'
        ]);
        $json = '{"component":"type(string)","props":{"key2":"value2"},"url":"callback()","version":null}';
        $jsonResponse = null;

        $uri = $this->prophesize(UriInterface::class);
        $request->getUri()->willReturn(Argument::that([$uri, 'reveal']));

        $response = $this->prophesize(ResponseInterface::class);
        $responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $responseFactory->createResponse()->willReturn($response);

        $stream = $this->prophesize(StreamInterface::class);
        $streamFactory = $this->prophesize(StreamFactoryInterface::class);
        $streamFactory->createStream(Argument::type('string'))->will(function ($args) use (&$jsonResponse, $stream){
            $jsonResponse = $args[0];
            return $stream;
        });

        $rootViewProvider = $this->prophesize(RootViewProviderInterface::class);

        $response->withBody($stream->reveal())->willReturn($response);
        $response->withHeader('X-Inertia', true)->willReturn($response);
        $response->withHeader('Content-Type', 'application/json')->willReturn($response);

        $inertia = new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );

        $returnedResponse = $inertia->render(
            Argument::type('string'),
            [
                'key1' => fn() => 'value1',
                'key2' => fn() => 'value2'
            ]
        );

        $this->assertInstanceOf(ResponseInterface::class, $returnedResponse);
        $this->assertSame($json, $jsonResponse);
    }

}