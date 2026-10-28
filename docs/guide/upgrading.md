# Upgrading to 2.0

2.0 implements the protocol of Inertia.js 3 and gives the package the layout of the other Solo packages. Nothing of the 1.x API stays as it was, but an application touches few places: the service definition, the root template, the middleware entry and the code that shares props. Controllers that call `render()` keep working.

## Requirements

- PHP 8.3 or later (1.x: 8.1).
- An **Inertia.js 3** client. 2.0 puts the first page in a `<script type="application/json">` element; clients before 3.0 read the `data-page` attribute 1.x wrote, and do not find the page.
- `psr/http-factory` and `psr/log` are new dependencies; `psr/container` is gone.

## Namespace

**1.x:** the service was `Solo\Inertia`, autoloaded from `Solo/`, next to `Solo\Inertia\InertiaMiddleware`.

**2.0:** every class is under `Solo\Inertia\`, autoloaded from `src/`:

```php
// 1.x
use Solo\Inertia;

// 2.0
use Solo\Inertia\Inertia;
```

`Solo\Inertia\InertiaMiddleware` keeps its name.

## The service

**1.x:** the constructor took a template path, the asset version and one JavaScript and one CSS path:

```php
new Inertia('/path/to/root.php', $version, '/build/app.js', '/build/app.css');
```

**2.0:** it takes a root view and named options. The asset paths are no longer the package's business: pass whatever the template needs as template data.

```php
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;

new Inertia(
    new PhpRootView('/path/to/root.php', ['js' => '/build/app.js', 'css' => '/build/app.css']),
    version: $version,
    flash: new CookieFlashStore($appKey), // for errors and flash data, see below
);
```

## The root template

**1.x:** the template got `$app['page']`, the page object as JSON for a `data-page` attribute, and `$app['js']`, `$app['css']`, `$app['props']`:

```php
<link href="<?= $app['css'] ?>" rel="stylesheet">
<script src="<?= $app['js'] ?>" defer></script>
...
<div id="app" data-page='<?= $app['page'] ?>'></div>
```

**2.0:** it gets `$body`, the markup Inertia 3 reads — the page object's script element and the root element — and `$head` for server-side rendering; `$page` is the page object as an array, its props included. Template data and view data are variables of their own:

```php
<link href="<?= $css ?>" rel="stylesheet">
<script src="<?= $js ?>" defer></script>
<?= $head ?>
...
<?= $body ?>
```

The JSON in the script element escapes `/`, `<` and `>`, as the protocol requires; it no longer uses `JSON_HEX_APOS` and `JSON_HEX_QUOT`, which were for the attribute.

## The middleware

**1.x:** `new InertiaMiddleware($assetsVersion)`. On a version mismatch the handler ran, and its response got an `X-Inertia-Location` header; every response to an Inertia request got `X-Inertia: true` and `Vary: Accept`.

**2.0:** `new InertiaMiddleware($inertia, $responseFactory)`, which a container autowires; the version comes from the `Inertia` service. A stale GET gets a `409 Conflict` before any handler runs, so the client reloads the page. `X-Inertia: true` is set by `render()` on page objects only — a 409 or a JSON API response must not carry it — and every response varies by `X-Inertia`. Redirects to a URL with a fragment become a 409 with `X-Inertia-Redirect`.

List it before your error middleware, so the redirects that one makes after a PUT are 303s too.

## Shared props

**1.x:** a middleware put an array in the `sharedProps` request attribute.

**2.0:** it calls `Inertia::share()`, which adds to what earlier middleware shared:

```php
// 1.x
return $handler->handle($request->withAttribute('sharedProps', ['user' => $user]));

// 2.0
return $handler->handle(Inertia::share($request, ['user' => $user]));
```

Shared props are listed in the page's `sharedProps`, which the client uses for instant visits.

## Closures

**1.x:** every Closure in the props, at any depth, was called before the partial reload filter, which compared top-level keys only.

**2.0:** a Closure is called only when the response sends its prop, and partial reloads select props by path, such as `auth.user`. Props that were never meant to be sent on every visit can now say so: [optional, deferred, always](/features/partial-reloads), [merge](/features/merging-props) and [once](/features/once-props) props.

## Errors and flash data

**1.x:** nothing; validation errors had to be shared by hand.

**2.0:** every page has an `errors` prop, `{}` without errors. `redirect()` and `back()` carry errors and flash data to the next page through a flash store; `CookieFlashStore` needs no session. See [validation](/features/validation) and [flash data](/features/flash-data).

## Removed

- `getRequestTarget()` as the page URL: the URL is now the path and query string of the request URI, with one leading slash.
- `JSON_HEX_APOS | JSON_HEX_QUOT` in JSON responses: page objects are plain JSON.
