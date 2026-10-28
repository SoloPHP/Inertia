<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Inertia\Header;
use Solo\Inertia\InertiaMiddleware;
use Solo\Inertia\Tests\Fixtures\CallbackHandler;

final class InertiaMiddlewareTest extends TestCase
{
    private static function process(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $version = '1',
    ): ResponseInterface {
        return (new InertiaMiddleware(self::inertia($version), new Psr17Factory()))
            ->process($request, new CallbackHandler(fn() => $response));
    }

    public function testMatchingVersionPassesThrough(): void
    {
        $response = self::process(self::xhr(version: '1'), self::response()->withHeader('X-Test', 'yes'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('yes', $response->getHeaderLine('X-Test'));
        self::assertSame('X-Inertia', $response->getHeaderLine('Vary'));
    }

    public function testVersionIsCheckedOnGetOnly(): void
    {
        self::assertSame(409, self::process(self::xhr(version: '0'), self::response())->getStatusCode());
        $post = self::xhr(method: 'POST', version: '0');
        self::assertSame(200, self::process($post, self::response())->getStatusCode());
        // A first visit loads the current assets anyway
        self::assertSame(200, self::process(self::visit(), self::response())->getStatusCode());
    }

    public function testUntrackedVersionMatchesAClientWithout(): void
    {
        $request = self::xhr()->withoutHeader(Header::VERSION);

        self::assertSame(200, self::process($request, self::response(), version: '')->getStatusCode());
    }

    /** @return iterable<string, array{string, int}> */
    public static function redirects(): iterable
    {
        yield 'PUT' => ['PUT', 303];
        yield 'PATCH' => ['PATCH', 303];
        yield 'DELETE' => ['DELETE', 303];
        yield 'POST' => ['POST', 302];
        yield 'GET' => ['GET', 302];
    }

    #[DataProvider('redirects')]
    public function testFoundAfterPutPatchOrDeleteBecomesSeeOther(string $method, int $status): void
    {
        $response = self::process(
            self::xhr(method: $method, version: '1'),
            self::response(302)->withHeader('Location', '/tasks'),
        );

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('/tasks', $response->getHeaderLine('Location'));
    }

    public function testNonInertiaRedirectIsLeftAlone(): void
    {
        $response = self::process(self::visit(method: 'DELETE'), self::response(302)->withHeader('Location', '/'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('X-Inertia', $response->getHeaderLine('Vary'));
    }

    public function testRedirectWithAFragmentBecomesA409AndKeepsItsCookies(): void
    {
        $response = self::process(
            self::xhr(method: 'PUT', version: '1'),
            self::response(302)
                ->withHeader('Location', '/article/new-slug#section')
                ->withHeader('Set-Cookie', 'inertia_flash=x; Path=/'),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('/article/new-slug#section', $response->getHeaderLine(Header::REDIRECT));
        self::assertFalse($response->hasHeader('Location'));
        self::assertSame('inertia_flash=x; Path=/', $response->getHeaderLine('Set-Cookie'));
    }

    public function testPrefetchedRedirectKeepsItsFragment(): void
    {
        $response = self::process(
            self::xhr(headers: [Header::PURPOSE => 'prefetch'], version: '1'),
            self::response(302)->withHeader('Location', '/article#section'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/article#section', $response->getHeaderLine('Location'));
    }

    public function testVaryIsAddedOnce(): void
    {
        $response = self::process(self::xhr(version: '1'), self::response()->withHeader('Vary', 'Accept, x-inertia'));

        self::assertSame(['Accept, x-inertia'], $response->getHeader('Vary'));
    }

    public function testHandlerResponsesAreNotMarkedAsPages(): void
    {
        $response = self::process(self::xhr(version: '1'), self::response(500));

        self::assertFalse($response->hasHeader(Header::INERTIA));
    }
}
