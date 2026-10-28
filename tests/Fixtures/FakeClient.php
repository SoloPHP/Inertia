<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests\Fixtures;

use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers with a callback, and remembers the requests it got.
 */
final class FakeClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param Closure(RequestInterface): ResponseInterface $answer */
    public function __construct(private readonly Closure $answer)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return ($this->answer)($request);
    }
}
