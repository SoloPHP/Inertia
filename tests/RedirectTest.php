<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\Flash;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;

final class RedirectTest extends TestCase
{
    public function testRedirectWithoutFlashSetsNoCookie(): void
    {
        $response = self::inertia()->redirect(self::xhr(method: 'POST'), self::response(), '/tasks');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/tasks', $response->getHeaderLine('Location'));
        self::assertSame([], self::setCookies($response));
    }

    public function testRedirectCarriesFlashToTheNextRequest(): void
    {
        $store = new CookieFlashStore(self::SECRET);

        $response = self::inertia(flash: $store)->redirect(
            self::xhr(method: 'POST'),
            self::response(),
            '/tasks',
            flash: ['message' => 'Created'],
            errors: ['title' => 'Too long'],
            clearHistory: true,
            preserveFragment: true,
            status: 303,
        );

        self::assertSame(303, $response->getStatusCode());
        $cookie = self::cookieValue(self::setCookies($response)['inertia_flash']);
        self::assertEquals(
            new Flash(['message' => 'Created'], ['title' => 'Too long'], true, true),
            $store->get(self::xhr(cookies: ['inertia_flash' => $cookie])),
        );
    }

    public function testRedirectKeepsWhatAnEarlierRedirectLeft(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $flash = new Flash(['message' => 'Signed in', 'level' => 'info']);
        $first = $store->put(self::xhr(), self::response(302), $flash);
        $cookie = self::cookieValue(self::setCookies($first)['inertia_flash']);
        $request = self::xhr(cookies: ['inertia_flash' => $cookie]);

        $second = self::inertia(flash: $store)
            ->redirect($request, self::response(), '/home', flash: ['level' => 'success']);

        $cookie = self::cookieValue(self::setCookies($second)['inertia_flash']);
        self::assertSame(
            ['message' => 'Signed in', 'level' => 'success'],
            $store->get(self::xhr(cookies: ['inertia_flash' => $cookie]))->data,
        );
    }

    public function testFlashingNeedsAStore(): void
    {
        $inertia = new Inertia(new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'));

        self::assertSame(302, $inertia->redirect(self::xhr(), self::response(), '/')->getStatusCode());

        $this->expectException(LogicException::class);
        $inertia->redirect(self::xhr(), self::response(), '/', errors: ['title' => 'Required']);
    }

    public function testRedirectNeedsARedirectStatus(): void
    {
        foreach ([200, 300, 304] as $status) {
            try {
                self::inertia()->redirect(self::xhr(), self::response(), '/', status: $status);
                self::fail("$status is not a redirect");
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function referers(): iterable
    {
        yield 'same host' => ['http://localhost/tasks/create?from=list', '/tasks/create?from=list'];
        yield 'same host, other scheme and port' => ['https://LOCALHOST:8443/tasks', '/tasks'];
        yield 'relative' => ['/tasks/1/edit', '/tasks/1/edit'];
        yield 'another host' => ['https://evil.example/phish', '/fallback'];
        yield 'protocol-relative' => ['//evil.example/phish', '/fallback'];
        yield 'backslash path on this host' => ['http://localhost/\\evil.example', '/evil.example'];
        yield 'relative backslash path' => ['/\\evil.example', '/evil.example'];
        yield 'not a URL' => ['javascript:alert(1)', '/fallback'];
        yield 'missing' => ['', '/fallback'];
    }

    #[DataProvider('referers')]
    public function testBackGoesToTheRefererOnThisHost(string $referer, string $location): void
    {
        $request = self::xhr('http://localhost/tasks', 'POST', headers: array_filter(['Referer' => $referer]));

        $response = self::inertia()->back($request, self::response(), '/fallback');

        self::assertSame($location, $response->getHeaderLine('Location'));
    }

    public function testBackCarriesErrors(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $request = self::xhr('http://localhost/tasks', 'POST', headers: ['Referer' => 'http://localhost/tasks/create']);

        $response = self::inertia(flash: $store)->back($request, self::response(), errors: ['title' => 'Required']);

        $cookie = self::cookieValue(self::setCookies($response)['inertia_flash']);
        self::assertSame('/tasks/create', $response->getHeaderLine('Location'));
        $next = self::xhr(cookies: ['inertia_flash' => $cookie]);
        self::assertSame(['title' => 'Required'], $store->get($next)->errors);
    }

    public function testLocationIsA409OnAnInertiaVisit(): void
    {
        $response = self::inertia()->location(self::xhr(), self::response(), 'https://github.com/SoloPHP');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://github.com/SoloPHP', $response->getHeaderLine(Header::LOCATION));
        self::assertFalse($response->hasHeader('Location'));
        self::assertFalse($response->hasHeader(Header::INERTIA));
    }

    public function testLocationIsARedirectOtherwise(): void
    {
        $response = self::inertia()->location(self::visit(), self::response(), 'https://github.com/SoloPHP');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://github.com/SoloPHP', $response->getHeaderLine('Location'));
    }
}
