<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use Solo\Inertia\Contracts\RequestValidatorInterface;

/**
 * Finds the errors it was given, and remembers the requests it checked.
 */
final class ArrayValidator implements RequestValidatorInterface
{
    /** @var list<ServerRequestInterface> */
    public array $requests = [];

    /** @param array<string, mixed> $errors */
    public function __construct(private readonly array $errors = [])
    {
    }

    public function errors(ServerRequestInterface $request): array
    {
        $this->requests[] = $request;

        return $this->errors;
    }
}
