<?php declare(strict_types=1);

namespace Solo\Inertia;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for handling Inertia.js requests and responses.
 *
 * This middleware enforces Inertia protocol requirements by:
 * - Checking asset version mismatches and triggering client-side redirects
 * - Adjusting redirect status codes for non-GET requests
 * - Setting appropriate Inertia headers
 *
 * @see https://inertiajs.com/the-protocol
 */
final class InertiaMiddleware implements MiddlewareInterface
{
    /**
     * Initialize the middleware with assets version for cache busting.
     *
     * @param string $assetsVersion Version identifier for detecting asset changes
     */
    public function __construct(
        private readonly string $assetsVersion
    )
    {
    }

    /**
     * Process an incoming server request.
     *
     * Handles Inertia-specific protocol requirements:
     * - Triggers full page reload when asset versions mismatch
     * - Converts 302 redirects to 303 for PUT/PATCH/DELETE requests
     * - Adds appropriate Inertia headers to responses
     *
     * @param ServerRequestInterface $request The server request
     * @param RequestHandlerInterface $handler The request handler
     * @return ResponseInterface The processed response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('X-Inertia')) {
            return $handler->handle($request);
        }

        $response = $handler->handle($request);

        if ($request->getMethod() === 'GET' && $request->getHeaderLine('X-Inertia-Version') !== $this->assetsVersion) {
            return $response->withAddedHeader('X-Inertia-Location', $request->getUri()->getPath());
        }

        if ($response->getStatusCode() === 302 && in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'])) {
            $response = $response->withStatus(303);
        }

        return $response->withHeader('Vary', 'Accept')->withHeader('X-Inertia', 'true');
    }
}