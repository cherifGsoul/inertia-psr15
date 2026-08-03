<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function in_array;

class InertiaMiddleware implements MiddlewareInterface
{
    public const INERTIA_ATTRIBUTE = 'inertia';

    /**
     * InertiaMiddleware constructor.
     */
    public function __construct(
        private readonly InertiaFactoryInterface $inertiaFactory,
        private readonly string $attributeKey = self::INERTIA_ATTRIBUTE
    ) {}

    public function process(Request $request, Handler $handler): Response
    {
        $inertia = $this->inertiaFactory->fromRequest($request);

        $request = $request->withAttribute($this->attributeKey, $inertia);

        if (! $request->hasHeader('X-Inertia')) {
            return $handler->handle($request);
        }

        /** @var Response */
        $response = $handler->handle($request)
            ->withAddedHeader('Vary', 'X-Inertia')
            ->withAddedHeader('X-Inertia', 'true')
        ;
        $response = $this->checkVersion($request, $response, $inertia);

        return $this->changeRedirectCode($request, $response);
    }

    private function checkVersion(Request $request, Response $response, InertiaInterface $inertia): Response
    {
        if (
            'GET' === $request->getMethod()
            && $request->getHeaderLine('X-Inertia-Version') !== (string) $inertia->getVersion()
        ) {
            return $response
                ->withStatus(409)
                ->withHeader('X-Inertia-Location', (string) $request->getUri())
            ;
        }

        return $response;
    }

    private function changeRedirectCode(Request $request, Response $response): Response
    {
        if (! $request->hasHeader('X-Inertia')) {
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
