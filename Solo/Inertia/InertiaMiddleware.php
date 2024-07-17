<?php declare(strict_types=1);

namespace Solo\Inertia;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InertiaMiddleware implements MiddlewareInterface
{
    private string $assetsVersion;
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->assetsVersion = $container->get('assetsVersion');
    }

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