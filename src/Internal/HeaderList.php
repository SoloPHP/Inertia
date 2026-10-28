<?php

declare(strict_types=1);

namespace Solo\Inertia\Internal;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class HeaderList
{
    /** @return list<string> The comma-separated values of a request header, without blanks */
    public static function of(ServerRequestInterface $request, string $header): array
    {
        return self::split(...$request->getHeader($header));
    }

    /** @return list<string> The comma-separated values of header lines, without blanks */
    public static function split(string ...$lines): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', implode(',', $lines))),
            static fn(string $value): bool => $value !== '',
        ));
    }
}
