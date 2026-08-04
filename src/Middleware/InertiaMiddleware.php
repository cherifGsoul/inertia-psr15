<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function explode;
use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function str_contains;
use function strcasecmp;
use function strlen;
use function trim;

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

        $response = $handler->handle($request);

        $response = $this->withInertiaVary($response);

        if (! $request->hasHeader('X-Inertia')) {
            return $response;
        }

        $response = $response->withAddedHeader('X-Inertia', 'true');
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
                ->withHeader('X-Inertia-Location', $this->requestLocation($request))
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
            $response = $response->withStatus(303);
        }

        if (
            300 <= $response->getStatusCode()
            && 400 > $response->getStatusCode()
            && $response->hasHeader('Location')
            && 'prefetch' !== $request->getHeaderLine('Purpose')
        ) {
            $location = $response->getHeaderLine('Location');
            if (str_contains($location, '#') && $this->isSafeRedirectLocation($location)) {
                return $response
                    ->withStatus(409)
                    ->withHeader('X-Inertia-Redirect', $location)
                    ->withoutHeader('Location')
                ;
            }
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

    private function withInertiaVary(Response $response): Response
    {
        $tokens = [];
        foreach (explode(',', $response->getHeaderLine('Vary')) as $token) {
            $token = trim($token);
            if ('' !== $token) {
                $tokens[] = $token;
            }
        }

        foreach ($tokens as $token) {
            if (0 === strcasecmp($token, 'X-Inertia')) {
                return $response;
            }
        }

        if ([] === $tokens) {
            return $response->withAddedHeader('Vary', 'X-Inertia');
        }

        $tokens[] = 'X-Inertia';

        return $response->withHeader('Vary', implode(', ', $tokens));
    }

    private function isSafeRedirectLocation(string $location): bool
    {
        return '' !== $location
            && 8192 >= strlen($location)
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $location);
    }

    private function requestLocation(Request $request): string
    {
        $uri      = $request->getUri();
        $path     = '/' . ltrim($uri->getPath(), '/\\');
        $query    = $uri->getQuery();
        $location = $path . ('' === $query ? '' : '?' . $query);

        if (8192 < strlen($location) || str_contains($location, '\\') || 1 === preg_match('/[\x00-\x1F\x7F]/', $location)) {
            return '/';
        }

        return $location;
    }
}
