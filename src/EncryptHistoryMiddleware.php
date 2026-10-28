<?php

declare(strict_types=1);

namespace Solo\Inertia;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Encrypts the history state of the pages rendered below it: on a route group, the
 * pages a signed-out user must not see again with the back button.
 */
final readonly class EncryptHistoryMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute(Inertia::ENCRYPT_HISTORY, true));
    }
}
