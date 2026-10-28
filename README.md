# Solo Inertia

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/inertia.svg)](https://packagist.org/packages/solophp/inertia)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

An [Inertia.js 3](https://inertiajs.com) server adapter for PSR-7 and PSR-15: build a single-page app with Svelte, Vue or React and server-side routing and controllers, in any PSR framework. It implements the whole [protocol](https://inertiajs.com/docs/v3/core-concepts/the-protocol): partial reloads, deferred, optional, always, merge, once and infinite scroll props, version conflicts, external and fragment redirects, validation errors and error bags, flash data, history encryption, Precognition and server-side rendering.

> Upgrading from 1.x? Read the [upgrade guide](https://solophp.github.io/Inertia/guide/upgrading). 2.0 speaks the Inertia 3 protocol and needs an Inertia 3 client.

## Installation

```bash
composer require solophp/inertia
```

Requires PHP 8.3+, a PSR-7 implementation with PSR-17 factories, and an Inertia.js 3 client such as `@inertiajs/svelte`.

## Example

```php
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;

$inertia = new Inertia(
    new PhpRootView(__DIR__ . '/resources/views/app.php'),
    version: $assetVersion,
    flash: new CookieFlashStore($appKey),
);

// A handler: the HTML document on a first visit, the page object as JSON afterwards
return $inertia->render($request, $response, 'Tasks/Index', [
    'tasks' => fn() => $tasks->all(),                 // called only when sent
    'stats' => Inertia::defer(fn() => $tasks->stats()), // loaded right after the page
]);

// After a form: errors back to it, or a redirect with a message
return $inertia->back($request, $response, errors: ['title' => 'The title is required.']);
return $inertia->redirect($request, $response, '/tasks', flash: ['success' => 'Task created.']);
```

`InertiaMiddleware` handles stale assets (a `409` before any handler runs), turns a `302` after `PUT`, `PATCH` or `DELETE` into a `303`, and makes redirects with a fragment reach the client. Shared props come from any middleware: `$handler->handle(Inertia::share($request, ['auth.user' => fn() => $user]))`.

The [Svelte skeleton](https://github.com/SoloPHP/Inertia-Svelte-Skeleton) is a complete application built with it.

## Documentation

**[Full Documentation](https://solophp.github.io/Inertia/)**

- [Installation](https://solophp.github.io/Inertia/guide/installation)
- [Quick Start](https://solophp.github.io/Inertia/guide/quick-start)
- [Upgrading from 1.x](https://solophp.github.io/Inertia/guide/upgrading)
- [Responses](https://solophp.github.io/Inertia/features/responses) and [Root Template](https://solophp.github.io/Inertia/features/root-template)
- [Shared Data](https://solophp.github.io/Inertia/features/shared-data) and [Partial Reloads](https://solophp.github.io/Inertia/features/partial-reloads)
- [Deferred](https://solophp.github.io/Inertia/features/deferred-props), [Merging](https://solophp.github.io/Inertia/features/merging-props) and [Once](https://solophp.github.io/Inertia/features/once-props) Props, [Infinite Scroll](https://solophp.github.io/Inertia/features/infinite-scroll)
- [Redirects](https://solophp.github.io/Inertia/features/redirects), [Validation and Precognition](https://solophp.github.io/Inertia/features/validation) and [Flash Data](https://solophp.github.io/Inertia/features/flash-data)
- [History Encryption](https://solophp.github.io/Inertia/features/history-encryption), [Asset Versioning](https://solophp.github.io/Inertia/features/asset-versioning) and [Server-Side Rendering](https://solophp.github.io/Inertia/features/ssr)
- [Testing](https://solophp.github.io/Inertia/features/testing) and the [API Reference](https://solophp.github.io/Inertia/api/inertia)

## License

MIT. See [LICENSE](LICENSE).
