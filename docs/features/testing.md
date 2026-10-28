# Testing

A page is a PSR-7 response, so a test sends a request through the application and reads the page object from the response. Send the headers the client sends, with the `Header` constants:

```php
use Nyholm\Psr7\ServerRequest;
use Solo\Inertia\Header;

$request = (new ServerRequest('GET', '/tasks'))
    ->withHeader(Header::INERTIA, 'true')
    ->withHeader(Header::VERSION, $inertia->version);

$response = $app->handle($request);

self::assertSame('true', $response->getHeaderLine(Header::INERTIA));
$page = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

self::assertSame('Tasks/Index', $page['component']);
self::assertCount(3, $page['props']['tasks']);
```

Without `X-Inertia-Version`, a GET to an application with a version gets a `409`: send the version of the `Inertia` service, or construct it with the version `''` in tests.

## A first visit

Without `X-Inertia`, the response is the HTML document; the page object is in its script element:

```php
preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', (string) $response->getBody(), $m);
$page = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
```

## Partial reloads and deferred props

```php
$request = $request
    ->withHeader(Header::PARTIAL_COMPONENT, 'Tasks/Index')
    ->withHeader(Header::PARTIAL_DATA, 'stats');
```

A deferred prop is in `$page['deferredProps']` on the full visit and in `$page['props']` on the reload.

## Forms

A form test posts, follows the redirect and reads the next page, carrying the cookies a response sets to the next request, as the browser does: `CookieFlashStore` keeps errors and flash data in one.

```php
$redirect = $app->handle($post->withHeader('Referer', 'http://localhost/tasks'));
self::assertSame(302, $redirect->getStatusCode());

// Set-Cookie: inertia_flash=<value>; Path=/; HttpOnly; SameSite=Lax
preg_match('/^inertia_flash=([^;]*)/', $redirect->getHeaderLine('Set-Cookie'), $cookie);

$next = $app->handle($get->withCookieParams(['inertia_flash' => $cookie[1]]));
// $page['props']['errors'], $page['flash']
```

The [Svelte skeleton](https://github.com/SoloPHP/Inertia-Svelte-Skeleton)'s `tests/TestCase.php` wraps this in a few helpers.
