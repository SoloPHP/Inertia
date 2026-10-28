<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\PrecognitionMiddleware;
use Solo\Inertia\Tests\Fixtures\ArrayValidator;
use Solo\Inertia\Tests\Fixtures\CallbackHandler;

final class PrecognitionMiddlewareTest extends TestCase
{
    private const array ERRORS = [
        'name' => 'The name is required.',
        'address.city' => ['The city is required.', 'The city is too short.'],
        'email' => 'The email is invalid.',
    ];

    /** @param array<string, mixed> $errors */
    private static function process(
        ServerRequestInterface $request,
        array $errors = self::ERRORS,
        ?ArrayValidator $validator = null,
    ): ResponseInterface {
        $handler = new CallbackHandler(
            fn() => throw new RuntimeException('A Precognition request never runs the handler'),
        );

        return (new PrecognitionMiddleware($validator ?? new ArrayValidator($errors), new Psr17Factory()))
            ->process($request, $handler);
    }

    /** @param list<string> $only */
    private static function precognition(array $only = []): ServerRequestInterface
    {
        return self::visit('http://localhost/users', 'POST', array_filter([
            Header::PRECOGNITION => 'true',
            Header::PRECOGNITION_VALIDATE_ONLY => implode(',', $only),
            'Accept' => 'application/json',
        ]));
    }

    public function testInvalidFieldsAreA422WithTheirErrors(): void
    {
        $response = self::process(self::precognition(['name', 'address']));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('true', $response->getHeaderLine(Header::PRECOGNITION));
        self::assertSame('Precognition', $response->getHeaderLine('Vary'));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['errors' => [
            'name' => 'The name is required.',
            'address.city' => ['The city is required.', 'The city is too short.'],
        ]], self::decode((string) $response->getBody()));
    }

    public function testValidFieldsAreA204EvenWhenOthersFail(): void
    {
        $response = self::process(self::precognition(['phone ', ' age']));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('true', $response->getHeaderLine(Header::PRECOGNITION));
        self::assertSame('true', $response->getHeaderLine(Header::PRECOGNITION_SUCCESS));
        self::assertSame('', (string) $response->getBody());
    }

    public function testWithoutValidateOnlyEveryFieldCounts(): void
    {
        $failed = self::process(self::precognition());
        self::assertSame(self::ERRORS, self::decode((string) $failed->getBody())['errors']);

        self::assertSame(204, self::process(self::precognition(), errors: [])->getStatusCode());
    }

    public function testOtherRequestsReachTheHandler(): void
    {
        $validator = new ArrayValidator(self::ERRORS);
        $handler = new CallbackHandler(fn() => self::response(201));

        $response = (new PrecognitionMiddleware($validator, new Psr17Factory()))
            ->process(self::visit(method: 'POST'), $handler);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Precognition', $response->getHeaderLine('Vary'));
        self::assertFalse($response->hasHeader(Header::PRECOGNITION));
        self::assertSame([], $validator->requests);
    }

    public function testValidatorGetsTheRequest(): void
    {
        $request = self::precognition(['name']);
        $validator = new ArrayValidator();

        self::process($request, validator: $validator);

        self::assertSame([$request], $validator->requests);
    }

    public function testDetection(): void
    {
        self::assertTrue(Inertia::isPrecognitive(self::precognition()));
        self::assertFalse(Inertia::isPrecognitive(self::visit(headers: [Header::PRECOGNITION => 'false'])));
        self::assertFalse(Inertia::isPrecognitive(self::visit()));
    }
}
