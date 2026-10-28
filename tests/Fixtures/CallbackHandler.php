<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests\Fixtures;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A handler that answers with a callback, and remembers the request it got.
 */
final class CallbackHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    /** @param Closure(ServerRequestInterface): ResponseInterface $callback */
    public function __construct(private readonly Closure $callback)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return ($this->callback)($request);
    }
}
