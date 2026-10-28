<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use InvalidArgumentException;
use JsonSerializable;
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\EncryptHistoryMiddleware;
use Solo\Inertia\Flash;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;
use Solo\Inertia\Tests\Fixtures\Page;

final class RenderTest extends TestCase
{
    public function testEmptyPropsAndErrorsAreObjects(): void
    {
        $response = self::inertia()->render(self::xhr(), self::response(), 'Home');

        self::assertSame(
            '{"component":"Home","props":{"errors":{}},"url":"/","version":"","sharedProps":["errors"]}',
            (string) $response->getBody(),
        );
    }

    public function testClosuresAreCalledAndJsonSerializableValuesSerialized(): void
    {
        $page = self::pageOf(self::xhr(), 'Home', [
            'count' => fn() => 3,
            'nested' => ['deep' => fn() => ['deeper' => fn() => 'yes']],
            'value' => new class implements JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return ['lazy' => fn() => 'resolved'];
                }
            },
        ]);

        self::assertSame([
            'errors' => [],
            'count' => 3,
            'nested' => ['deep' => ['deeper' => 'yes']],
            'value' => ['lazy' => 'resolved'],
        ], $page['props']);
    }

    public function testDottedKeysNest(): void
    {
        $request = Inertia::share(self::xhr(), [
            'auth' => fn() => ['user' => ['name' => 'Ada']],
            'app.name' => 'Solo',
        ]);

        $page = self::pageOf($request, 'Home', [
            'auth.permissions' => ['edit'],
        ]);

        self::assertSame([
            'errors' => [],
            'auth' => ['user' => ['name' => 'Ada'], 'permissions' => ['edit']],
            'app' => ['name' => 'Solo'],
        ], $page['props']);
        self::assertSame(['errors', 'auth', 'app'], $page['sharedProps']);
    }

    public function testDottedKeyCannotNestIntoAValue(): void
    {
        $this->expectExceptionMessage('Prop auth.user cannot be set: auth is not an array');

        self::inertia()->render(self::xhr(), self::response(), 'Home', ['auth' => 'guest', 'auth.user' => 'Ada']);
    }

    public function testPagePropsWinOverSharedOnesAndLaterSharesOverEarlier(): void
    {
        $request = Inertia::share(Inertia::share(self::xhr(), ['a' => 1, 'b' => 1]), ['b' => 2, 'c' => 2]);

        $page = self::pageOf($request, 'Home', ['c' => 3]);

        self::assertSame(['errors' => [], 'a' => 1, 'b' => 2, 'c' => 3], $page['props']);
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 2], Inertia::shared($request));
    }

    public function testUrlIsThePathAndQueryOfTheRequest(): void
    {
        $page = self::page(self::inertia()->render(
            self::xhr('https://example.com/users/?sort=name&page=2'),
            self::response(),
            'Users',
        ));

        self::assertSame('/users/?sort=name&page=2', $page['url']);
        self::assertSame('/', self::pageOf(self::xhr('https://example.com'), 'Home')['url']);
    }

    public function testResponseKeepsItsStatusAndHeaders(): void
    {
        $response = self::inertia()->render(
            self::xhr(),
            self::response(404)->withHeader('Vary', 'Accept-Encoding'),
            'Error',
            ['status' => 404],
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['Accept-Encoding', 'X-Inertia'], $response->getHeader('Vary'));
    }

    public function testComponentMayBeABackedEnum(): void
    {
        $page = self::pageOf(self::xhr(), Page::UsersIndex);

        self::assertSame('Users/Index', $page['component']);
    }

    public function testComponentMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::inertia()->render(self::xhr(), self::response(), '');
    }

    public function testScriptElementCannotBeClosedByAProp(): void
    {
        $response = self::inertia()->render(self::visit(), self::response(), 'Home', [
            'html' => '</script><script>alert(1)</script><!--',
        ]);

        $html = (string) $response->getBody();
        self::assertStringNotContainsString('</script><script>', $html);
        self::assertStringNotContainsString('<!--', $html);
        self::assertSame('</script><script>alert(1)</script><!--', self::props(self::htmlPage($response))['html']);
    }

    public function testRootTemplateGetsViewDataAndTheRootId(): void
    {
        $inertia = new Inertia(new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'), rootId: 'my-app');

        $html = (string) $inertia->render(self::visit(), self::response(), 'Home', viewData: ['title' => 'Welcome'])
            ->getBody();

        self::assertStringContainsString('<title>Welcome</title>', $html);
        self::assertStringContainsString('<script data-page="my-app" type="application/json">', $html);
        self::assertStringContainsString('<div id="my-app"></div>', $html);
        self::assertStringNotContainsString('Welcome', explode('<body>', $html)[1]);
    }

    public function testHistoryFlags(): void
    {
        $inertia = self::inertia();

        $plain = self::page($inertia->render(self::xhr(), self::response(), 'Home'));
        self::assertArrayNotHasKey('encryptHistory', $plain);
        self::assertArrayNotHasKey('clearHistory', $plain);

        $page = self::page(
            $inertia->render(self::xhr(), self::response(), 'Home', encryptHistory: true, clearHistory: true),
        );
        self::assertTrue($page['encryptHistory']);
        self::assertTrue($page['clearHistory']);

        $request = self::xhr()->withAttribute(Inertia::ENCRYPT_HISTORY, true);
        $encrypted = $inertia->render($request, self::response(), 'Home');
        self::assertTrue(self::page($encrypted)['encryptHistory']);

        $optedOut = self::inertia(encryptHistory: true)
            ->render(self::xhr(), self::response(), 'Home', encryptHistory: false);
        self::assertArrayNotHasKey('encryptHistory', self::page($optedOut));
    }

    public function testEncryptHistoryMiddlewareEncryptsThePagesBelowIt(): void
    {
        $inertia = self::inertia();
        $handler = new Fixtures\CallbackHandler(
            fn($request) => $inertia->render($request, self::response(), 'Account'),
        );

        $response = (new EncryptHistoryMiddleware())->process(self::xhr(), $handler);

        self::assertTrue(self::page($response)['encryptHistory']);
    }

    public function testFlashedDataIsShownOnceThenForgotten(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $redirect = $store->put(self::xhr(), self::response(302), new Flash(
            data: ['message' => 'Saved'],
            errors: ['title' => 'Required'],
            clearHistory: true,
            preserveFragment: true,
        ));
        $cookie = self::cookieValue(self::setCookies($redirect)['inertia_flash']);

        $response = self::inertia(flash: $store)->render(
            self::xhr(cookies: ['inertia_flash' => $cookie]),
            self::response(),
            'Home',
            flash: ['highlight' => 7],
        );

        $page = self::page($response);
        self::assertSame(['message' => 'Saved', 'highlight' => 7], $page['flash']);
        self::assertSame(['title' => 'Required'], self::props($page)['errors']);
        self::assertTrue($page['clearHistory']);
        self::assertTrue($page['preserveFragment']);
        self::assertStringContainsString('Max-Age=0', self::setCookies($response)['inertia_flash']);
    }

    public function testErrorsGoUnderTheErrorBagOfTheRequest(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $redirect = $store->put(self::xhr(), self::response(302), new Flash(errors: ['name' => 'Required']));
        $cookie = self::cookieValue(self::setCookies($redirect)['inertia_flash']);

        $page = self::page(self::inertia(flash: $store)->render(
            self::xhr(headers: [Header::ERROR_BAG => 'createUser'], cookies: ['inertia_flash' => $cookie]),
            self::response(),
            'Users/Create',
        ));

        self::assertSame(['createUser' => ['name' => 'Required']], self::props($page)['errors']);
    }

    public function testPrefetchLeavesFlashedDataForTheRealVisit(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $redirect = $store->put(self::xhr(), self::response(302), new Flash(data: ['message' => 'Saved']));
        $cookies = ['inertia_flash' => self::cookieValue(self::setCookies($redirect)['inertia_flash'])];

        $prefetched = self::inertia(flash: $store)->render(
            self::xhr(headers: [Header::PURPOSE => 'prefetch'], cookies: $cookies),
            self::response(),
            'Home',
        );

        self::assertArrayNotHasKey('flash', self::page($prefetched));
        self::assertSame([], self::setCookies($prefetched));
    }

    public function testWithoutAFlashStoreNothingIsFlashed(): void
    {
        $inertia = new Inertia(new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'));

        $response = $inertia->render(self::xhr(cookies: ['inertia_flash' => 'x']), self::response(), 'Home');

        self::assertSame(['errors' => []], self::props(self::page($response)));
        self::assertSame([], self::setCookies($response));
    }
}
