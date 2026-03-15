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
            return $handler->handle($request)->withAddedHeader('Vary', 'X-Inertia');
        }

        /** @var Response */
        $response = $handler->handle($request)
            ->withAddedHeader('Vary', 'X-Inertia')
            ->withAddedHeader('X-Inertia', 'true');
        $response = $this->checkVersion($request, $response);
        $response = $this->changeRedirectCode($request, $response);
        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function checkVersion(Request $request, Response $response): Response
    {
        $version = $this->inertia->getVersion();
        if (
            'GET' === $request->getMethod()
            && $version !== null
            && $request->getHeaderLine('X-Inertia-Version') !== (string)$version
        ) {
            return $response->withStatus(409)->withHeader('X-Inertia-Location', (string)$request->getUri());
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

        // For External redirects
        // https://inertiajs.com/redirects#external-redirects
        if (
            409 === $response->getStatusCode()
            && $response->hasHeader('X-Inertia-Location')
        ) {
            return $response->withoutHeader('X-Inertia');
        }

        return $response;
    }
}
