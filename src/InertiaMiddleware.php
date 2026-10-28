<?php

declare(strict_types=1);

namespace Solo\Inertia;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Inertia\Internal\Vary;

/**
 * The protocol rules that apply to a whole response, whoever made it:
 *
 * - a GET from a client built from other assets gets a 409 with X-Inertia-Location
 *   before any handler runs, and the client reloads the page;
 * - a 302 after a PUT, PATCH or DELETE becomes a 303, so the client follows it with a GET;
 * - a redirect to a URL with a fragment becomes a 409 with X-Inertia-Redirect, as an
 *   XHR would follow it and lose the fragment;
 * - every response varies by X-Inertia.
 *
 * List it before the middleware that turns exceptions into responses, so the
 * redirects and pages that one makes follow these rules too.
 */
final readonly class InertiaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Inertia $inertia,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Inertia::isInertia($request)) {
            return Vary::add($handler->handle($request));
        }

        $method = $request->getMethod();
        if ($method === 'GET' && $request->getHeaderLine(Header::VERSION) !== $this->inertia->version) {
            return Vary::add($this->responses->createResponse(409)
                ->withHeader(Header::LOCATION, (string) $request->getUri())
                ->withHeader(Header::VERSION, $this->inertia->version));
        }

        $response = Vary::add($handler->handle($request));
        $status = $response->getStatusCode();

        if ($status === 302 && in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            $response = $response->withStatus(303);
        }

        $location = $response->getHeaderLine('Location');
        if ($status >= 300 && $status < 400 && str_contains($location, '#') && !Inertia::isPrefetch($request)) {
            // The redirect's other headers stay, such as the cookie that carries its flash data
            $response = $response
                ->withStatus(409)
                ->withoutHeader('Location')
                ->withHeader(Header::REDIRECT, $location);
        }

        return $response;
    }
}
