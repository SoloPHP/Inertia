<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use InvalidArgumentException;
use LengthException;
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\Flash;

final class CookieFlashStoreTest extends TestCase
{
    private static function cookie(CookieFlashStore $store, Flash $flash, string $name = 'inertia_flash'): string
    {
        return self::cookieValue(self::setCookies($store->put(self::visit(), self::response(302), $flash))[$name]);
    }

    public function testRoundTrip(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $flash = new Flash(['message' => 'Привет, «мир»', 'id' => 7], ['title' => ['Required', 'Too short']], true);

        $request = self::visit(cookies: ['inertia_flash' => self::cookie($store, $flash)]);
        self::assertEquals($flash, $store->get($request));
    }

    public function testNoCookieIsAnEmptyFlash(): void
    {
        self::assertTrue((new CookieFlashStore(self::SECRET))->get(self::visit())->isEmpty());
    }

    public function testChangedOrForeignCookiesReadAsEmpty(): void
    {
        $store = new CookieFlashStore(self::SECRET);
        $value = self::cookie($store, new Flash(['message' => 'Saved']));
        [$payload, $signature] = explode('.', $value);
        $forged = rtrim(strtr(base64_encode('{"data":{"message":"Phish"}}'), '+/', '-_'), '=');

        foreach (
            [
                "$forged.$signature",
                $payload,
                "$payload.",
                "$payload.$signature-",
                self::cookie(new CookieFlashStore(str_repeat('x', 32)), new Flash(['message' => 'Saved'])),
                'garbage',
            ] as $cookie
        ) {
            self::assertTrue($store->get(self::visit(cookies: ['inertia_flash' => $cookie]))->isEmpty(), $cookie);
        }
    }

    public function testValueCannotMoveToAnotherCookie(): void
    {
        $value = self::cookie(new CookieFlashStore(self::SECRET, 'first'), new Flash(['a' => 1]), 'first');

        $other = new CookieFlashStore(self::SECRET, 'second');

        self::assertTrue($other->get(self::visit(cookies: ['second' => $value]))->isEmpty());
    }

    public function testCookieAttributes(): void
    {
        $store = new CookieFlashStore(self::SECRET, domain: 'example.com', path: '/app');
        $flash = new Flash(['a' => 1]);

        $http = $store->put(self::visit('http://example.com/app'), self::response(302), $flash);
        self::assertMatchesRegularExpression(
            '#^inertia_flash=[\w-]+\.[\w-]+; Path=/app; Domain=example.com; HttpOnly; SameSite=Lax$#',
            $http->getHeaderLine('Set-Cookie'),
        );

        $https = $store->put(self::visit('https://example.com/app'), self::response(302), $flash);
        self::assertStringContainsString('; Secure; HttpOnly', $https->getHeaderLine('Set-Cookie'));

        $insecure = (new CookieFlashStore(self::SECRET, secure: false))
            ->put(self::visit('https://example.com'), self::response(302), $flash);
        self::assertStringNotContainsString('Secure', $insecure->getHeaderLine('Set-Cookie'));
    }

    public function testForgetExpiresOnlyACookieThatWasSent(): void
    {
        $store = new CookieFlashStore(self::SECRET);

        self::assertFalse($store->forget(self::visit(), self::response())->hasHeader('Set-Cookie'));
        $forgotten = $store->forget(self::visit(cookies: ['inertia_flash' => 'x']), self::response());
        self::assertSame(
            'inertia_flash=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; HttpOnly; SameSite=Lax',
            $forgotten->getHeaderLine('Set-Cookie'),
        );
    }

    public function testPuttingNothingForgets(): void
    {
        $store = new CookieFlashStore(self::SECRET);

        $response = $store->put(self::visit(cookies: ['inertia_flash' => 'x']), self::response(302), new Flash());

        self::assertStringContainsString('Max-Age=0', $response->getHeaderLine('Set-Cookie'));
    }

    public function testAFlashTooLargeForACookieIsRefused(): void
    {
        $this->expectException(LengthException::class);

        (new CookieFlashStore(self::SECRET))->put(
            self::visit(),
            self::response(302),
            new Flash(errors: ['text' => str_repeat('x', 4000)]),
        );
    }

    public function testShortSecretIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 32 bytes');

        new CookieFlashStore('short');
    }

    public function testCookieNameMustSurvivePhpCookieParsing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CookieFlashStore(self::SECRET, 'inertia.flash');
    }

    public function testSameSiteNoneNeedsSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CookieFlashStore(self::SECRET, sameSite: 'None');
    }
}
