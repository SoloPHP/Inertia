# Asset Versioning

A client keeps running the JavaScript it loaded first, so after a deploy it would render new page objects with old code. The asset version prevents that: every page carries it, the client sends it back in `X-Inertia-Version`, and a GET from a client with another version gets a full page load instead of a page object.

```php
new Inertia($rootView, version: $version);
```

The version is any string that changes when the assets do, `''` when they are not tracked. The Vite manifest's hash is the usual choice:

```php
$manifest = $basePath . '/public/build/.vite/manifest.json';
$version = is_file($manifest) ? (string) hash_file('xxh128', $manifest) : '';
```

## The conflict

`InertiaMiddleware` compares the versions before any handler runs. On a mismatch, a GET from the client gets:

```http
HTTP/1.1 409 Conflict
X-Inertia-Location: https://example.com/events/80
X-Inertia-Version: 2f8a1c9e4b7d0a3f6c5e8b1d4a7f0c2e
```

The client loads that URL with `window.location`, gets the new assets with the HTML document, and the [flash data](/features/flash-data) a redirect left is still there for it.

Requests other than GET are not checked: a form posted from an old page still reaches its handler, and the GET the redirect leads to gets the conflict. A version change seen by a background request, such as polling, does not reload the page; the client waits for the next visit.
