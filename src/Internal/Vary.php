<?php

declare(strict_types=1);

namespace Solo\Inertia\Internal;

use Psr\Http\Message\ResponseInterface;
use Solo\Inertia\Header;

/**
 * @internal
 */
final class Vary
{
    /**
     * The response, marked as varying by $header: an HTML document and a page object
     * share a URL, and a cache must not answer one with the other.
     */
    public static function add(ResponseInterface $response, string $header = Header::INERTIA): ResponseInterface
    {
        foreach (HeaderList::split(...$response->getHeader('Vary')) as $value) {
            if ($value === '*' || strcasecmp($value, $header) === 0) {
                return $response;
            }
        }

        return $response->withAddedHeader('Vary', $header);
    }
}
