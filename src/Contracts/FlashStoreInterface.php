<?php

declare(strict_types=1);

namespace Solo\Inertia\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Inertia\Flash;

/**
 * Carries a Flash from the response that redirects to the request that renders the
 * next page. CookieFlashStore keeps it in a signed cookie; an implementation may
 * keep it in a session instead.
 */
interface FlashStoreInterface
{
    /** What an earlier response left for this request, or an empty Flash */
    public function get(ServerRequestInterface $request): Flash;

    /** The response, made to leave $flash for the next request; an empty Flash leaves nothing */
    public function put(ServerRequestInterface $request, ResponseInterface $response, Flash $flash): ResponseInterface;

    /** The response, made to drop what get() returned for this request, once a page has shown it */
    public function forget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
}
