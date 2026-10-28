<?php

declare(strict_types=1);

namespace Solo\Inertia;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Inertia\Contracts\RequestValidatorInterface;
use Solo\Inertia\Internal\HeaderList;
use Solo\Inertia\Internal\Json;
use Solo\Inertia\Internal\Paths;
use Solo\Inertia\Internal\Vary;

/**
 * Precognition: the client validates a form while the user fills it in, with
 * requests that carry `Precognition: true` and the fields to check in
 * Precognition-Validate-Only. Such a request never reaches the handler: the
 * validator checks it, and the answer is a 204 with Precognition-Success when
 * those fields are valid, or a 422 with their errors. Every other request goes on
 * to the handler, and every response varies by Precognition.
 *
 * Put it on the routes of the forms the client validates, as route or group
 * middleware, so the validator knows which route it validates for.
 */
final readonly class PrecognitionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestValidatorInterface $validator,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Inertia::isPrecognitive($request)) {
            return Vary::add($handler->handle($request), Header::PRECOGNITION);
        }

        $errors = self::requested($request, $this->validator->errors($request));

        if ($errors === []) {
            $response = $this->responses->createResponse(204)->withHeader(Header::PRECOGNITION_SUCCESS, 'true');
        } else {
            $response = $this->responses->createResponse(422)->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(['errors' => (object) $errors], Json::FLAGS));
        }

        return Vary::add($response->withHeader(Header::PRECOGNITION, 'true'), Header::PRECOGNITION);
    }

    /**
     * The errors of the fields the request asks about, a field's nested ones included;
     * all of them when it names none
     *
     * @param array<string, mixed> $errors
     * @return array<string, mixed>
     */
    private static function requested(ServerRequestInterface $request, array $errors): array
    {
        $fields = HeaderList::of($request, Header::PRECOGNITION_VALIDATE_ONLY);
        if ($fields === []) {
            return $errors;
        }

        return array_filter(
            $errors,
            static fn(int|string $key): bool => Paths::within((string) $key, $fields),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
