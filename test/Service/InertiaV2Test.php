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

class InertiaV2Test extends TestCase
{
    use ProphecyTrait;

    private function createInertia($request, &$jsonResponse = null)
    {
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
        $response->withHeader(Argument::any(), Argument::any())->willReturn($response);

        return new Inertia(
            $request->reveal(),
            $responseFactory->reveal(),
            $streamFactory->reveal(),
            $rootViewProvider->reveal()
        );
    }

    public function testPartialExcept()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(true);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);
        $request->getHeaderLine('X-Inertia-Partial-Component')->willReturn('component');
        $request->getHeaderLine('X-Inertia-Partial-Except')->willReturn('key1');

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $jsonResponse = null;
        $inertia = $this->createInertia($request, $jsonResponse);

        $inertia->render('component', [
            'key1' => 'value1',
            'key2' => 'value2'
        ]);

        $data = json_decode($jsonResponse, true);
        $this->assertArrayNotHasKey('key1', $data['props']);
        $this->assertArrayHasKey('key2', $data['props']);
    }

    public function testDefer()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $jsonResponse = null;
        $inertia = $this->createInertia($request, $jsonResponse);

        $inertia->defer('deferredKey', function() { return 'deferredValue'; }, 'myGroup');
        $inertia->render('component', ['regularKey' => 'regularValue']);

        $data = json_decode($jsonResponse, true);
        $this->assertArrayHasKey('deferredProps', $data);
        $this->assertEquals(['deferredKey'], $data['deferredProps']['myGroup']);
        $this->assertEquals('deferredValue', $data['props']['deferredKey']);
    }

    public function testMerge()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $jsonResponse = null;
        $inertia = $this->createInertia($request, $jsonResponse);

        $inertia->merge('mergedKey', 'mergedValue');
        $inertia->render('component');

        $data = json_decode($jsonResponse, true);
        $this->assertEquals(['mergedKey'], $data['mergeProps']);
        $this->assertEquals('mergedValue', $data['props']['mergedKey']);
    }

    public function testOnce()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $jsonResponse = null;
        $inertia = $this->createInertia($request, $jsonResponse);

        $inertia->once('onceKey', 'onceValue');
        $inertia->render('component');

        $data = json_decode($jsonResponse, true);
        $this->assertArrayHasKey('onceProps', $data);
        $this->assertEquals('onceKey', $data['onceProps']['onceKey']['prop']);
        $this->assertEquals('onceValue', $data['props']['onceKey']);
    }

    public function testReset()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(true);
        $request->getHeaderLine('X-Inertia-Reset')->willReturn('key1');

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $jsonResponse = null;
        $inertia = $this->createInertia($request, $jsonResponse);

        $inertia->render('component', [
            'key1' => 'value1',
            'key2' => 'value2'
        ]);

        $data = json_decode($jsonResponse, true);
        $this->assertArrayNotHasKey('key1', $data['props']);
        $this->assertArrayHasKey('key2', $data['props']);
    }

    public function testHistoryState()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->hasHeader('X-Inertia')->willReturn(true);
        $request->hasHeader('X-Inertia-Partial-Data')->willReturn(false);
        $request->hasHeader('X-Inertia-Partial-Except')->willReturn(false);
        $request->hasHeader('X-Inertia-Reset')->willReturn(false);

        $uri = $this->prophesize(UriInterface::class);
        $uri->__toString()->willReturn('http://example.com');
        $request->getUri()->willReturn($uri->reveal());

        $jsonResponse = null;
        $inertia = $this->createInertia($request, $jsonResponse);

        $inertia->encryptHistory()->clearHistory();
        $inertia->render('component');

        $data = json_decode($jsonResponse, true);
        $this->assertTrue($data['encryptHistory']);
        $this->assertTrue($data['clearHistory']);
    }
}
