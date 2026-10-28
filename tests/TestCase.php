<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use BackedEnum;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Inertia\Contracts\FlashStoreInterface;
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;

abstract class TestCase extends BaseTestCase
{
    protected const string SECRET = 'a-secret-of-at-least-thirty-two-bytes';

    protected static function inertia(
        string $version = '',
        ?FlashStoreInterface $flash = null,
        bool $encryptHistory = false,
    ): Inertia {
        return new Inertia(
            new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'),
            version: $version,
            flash: $flash ?? new CookieFlashStore(self::SECRET),
            encryptHistory: $encryptHistory,
        );
    }

    /**
     * A first visit, as a browser makes it
     *
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    protected static function visit(
        string $uri = 'http://localhost/',
        string $method = 'GET',
        array $headers = [],
        array $cookies = [],
    ): ServerRequestInterface {
        return (new ServerRequest($method, $uri, $headers))->withCookieParams($cookies);
    }

    /**
     * A visit the Inertia client makes, with its version header
     *
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    protected static function xhr(
        string $uri = 'http://localhost/',
        string $method = 'GET',
        array $headers = [],
        array $cookies = [],
        string $version = '',
    ): ServerRequestInterface {
        return self::visit($uri, $method, [
            Header::INERTIA => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            Header::VERSION => $version,
        ] + $headers, $cookies);
    }

    /**
     * A partial reload of $component
     *
     * @param list<string> $only
     * @param list<string> $except
     * @param array<string, string> $headers
     */
    protected static function reload(
        string $component,
        array $only = [],
        array $except = [],
        array $headers = [],
        string $uri = 'http://localhost/',
    ): ServerRequestInterface {
        return self::xhr($uri, headers: array_filter([
            Header::PARTIAL_COMPONENT => $component,
            Header::PARTIAL_DATA => implode(',', $only),
            Header::PARTIAL_EXCEPT => implode(',', $except),
        ]) + $headers);
    }

    protected static function response(int $status = 200): ResponseInterface
    {
        return (new Psr17Factory())->createResponse($status);
    }

    /**
     * The page object of a JSON response
     *
     * @return array<string, mixed>
     */
    protected static function page(ResponseInterface $response): array
    {
        self::assertSame('true', $response->getHeaderLine(Header::INERTIA));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        return self::decode((string) $response->getBody());
    }

    /**
     * The page object an Inertia visit gets for $component
     *
     * @param array<array-key, mixed> $props
     * @return array<string, mixed>
     */
    protected static function pageOf(
        ServerRequestInterface $request,
        string|BackedEnum $component,
        array $props = [],
    ): array {
        return self::page(self::inertia()->render($request, self::response(), $component, $props));
    }

    /**
     * The page object of an HTML response, from its script element
     *
     * @return array<string, mixed>
     */
    protected static function htmlPage(ResponseInterface $response): array
    {
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $html = (string) $response->getBody();
        self::assertSame(1, preg_match(
            '#<script data-page="app" type="application/json">(.*?)</script><div id="app"></div>#s',
            $html,
            $matches,
        ), $html);

        return self::decode($matches[1] ?? '');
    }

    /**
     * The page's props
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    protected static function props(array $page): array
    {
        $props = $page['props'] ?? null;
        self::assertIsArray($props);
        /** @var array<string, mixed> $props */
        return $props;
    }

    /**
     * The cookies a response sets, name => the whole Set-Cookie line
     *
     * @return array<string, string>
     */
    protected static function setCookies(ResponseInterface $response): array
    {
        $cookies = [];
        foreach ($response->getHeader('Set-Cookie') as $line) {
            $cookies[strstr($line, '=', true) ?: $line] = $line;
        }

        return $cookies;
    }

    /**
     * The value a Set-Cookie line sets, as the browser sends it back
     */
    protected static function cookieValue(string $setCookie): string
    {
        $pair = explode(';', $setCookie, 2)[0];

        return substr($pair, strpos($pair, '=') + 1);
    }

    /** @return array<string, mixed> */
    protected static function decode(string $json): array
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        /** @var array<string, mixed> $data */
        return $data;
    }
}
