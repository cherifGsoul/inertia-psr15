<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Middleware;

use Cherif\InertiaPsr15\Service\InertiaFactoryInterface;
use Cherif\InertiaPsr15\Service\InertiaInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class InertiaMiddleware implements MiddlewareInterface
{
    public const INERTIA_ATTRIBUTE = 'inertia';

    /**
     * @var InertiaFactoryInterface 
     */
    private InertiaFactoryInterface $inertiaFactory;

    /**
     * @var string 
     */
    private string $attributeKey;

    /**
     * @var InertiaInterface 
     */
    private InertiaInterface $inertia;

    /**
     * InertiaMiddleware constructor.
     * @param InertiaFactoryInterface $inertiaFactory
     * @param string $attributeKey
     */
    public function __construct(
        InertiaFactoryInterface $inertiaFactory,
        string $attributeKey = self::INERTIA_ATTRIBUTE
    )
    {
        $this->inertiaFactory = $inertiaFactory;
        $this->attributeKey = $attributeKey;
    }

    /**
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        $this->inertia = $this->inertiaFactory->fromRequest($request);
        
        $request = $request->withAttribute($this->attributeKey, $this->inertia);
        
        if (!$request->hasHeader('X-Inertia')) {
            return $handler->handle($request);
        }

        /** @var Response */
        $response = $handler->handle($request)
            ->withAddedHeader('Vary', 'Accept')
            ->withAddedHeader('X-Inertia', 'true');
        $response = $this->checkVersion($request, $response);
        $response = $this->changeRedirectCode($request, $response);
        return $response;
    }

    /**
     * @param $request
     * @param $response
     * @return Response
     */
    private function checkVersion($request, $response): Response
    {
        if (
            'GET' === $request->getMethod()
            && $request->getHeader('X-Inertia-Version') !== $this->inertia->getVersion()
        ) {
            return $response->withAddedHeader('X-Inertia-Location', $request->getUri()->getPath());
        }

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function changeRedirectCode(Request $request, Response $response): Response
    {
        if (!$request->hasHeader('X-Inertia')) {
            return $response;
        }

        if (
            302 === $response->getStatusCode()
            && in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'])
        ) {
            return $response->withStatus(303);
        }

        return $response;
    }
}
