<?php

declare(strict_types=1);

namespace Solo\Inertia\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Validates a request the way its handler would, without running the handler.
 * PrecognitionMiddleware asks it when a form is validated while the user fills
 * it in.
 */
interface RequestValidatorInterface
{
    /**
     * @return array<string, mixed> The errors, field => message or messages, as a redirect
     *                              carries them to a page; [] for a valid request
     */
    public function errors(ServerRequestInterface $request): array;
}
