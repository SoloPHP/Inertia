# Quick Start

This page wires the package into an application built on [solophp/application](https://solophp.github.io/Application/), as the [Svelte skeleton](https://github.com/SoloPHP/Inertia-Svelte-Skeleton) does. Any PSR-15 application takes the same three pieces: the `Inertia` service, `InertiaMiddleware` and a root template.

## 1. The service

`Inertia` holds configuration only, so one instance serves every request. Register it in a provider:

```php
<?php

namespace App\Providers;

use Psr\Log\LoggerInterface;
use Solo\Application\Config;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Dotenv\Dotenv;
use Solo\Inertia\CookieFlashStore;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;

final class InertiaServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(Inertia::class, function (WritableContainerInterface $c): Inertia {
            $basePath = $c->get(Config::class)->basePath;
            $manifest = $basePath . '/public/build/.vite/manifest.json';

            return new Inertia(
                new PhpRootView($basePath . '/resources/views/app.php'),
                // A client built from other assets reloads the page
                version: is_file($manifest) ? (string) hash_file('xxh128', $manifest) : '',
                // Errors and flash data reach the next page in a cookie signed with APP_KEY
                flash: new CookieFlashStore(Dotenv::required('APP_KEY')),
                logger: $c->get(LoggerInterface::class),
            );
        });
    }
}
```

`APP_KEY` is a random string of at least 32 bytes, such as `php -r "echo bin2hex(random_bytes(32));"`.

## 2. The root template

The first visit is a full HTML document. `PhpRootView` renders a PHP file with `$head`, `$body` and `$page`:

```php
<!-- resources/views/app.php -->
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script type="module" src="/build/assets/app.js"></script>
    <?= $head ?>
</head>
<body>
    <?= $body ?>
</body>
</html>
```

`$body` is the page object's script element and the root element the client mounts on. Load the built assets the way your build gives them; the [root template](/features/root-template) page shows a Vite manifest.

## 3. The middleware

List `InertiaMiddleware` before the middleware that turns exceptions into responses, so the redirects and error pages it makes follow the protocol too:

```php
// config/middleware.php
return [
    InertiaMiddleware::class,       // 409 for stale assets, 303 after PUT/PATCH/DELETE, fragments, Vary
    CsrfMiddleware::class,          // your own: refuses forms without the XSRF token the client echoes
    SharedPropsMiddleware::class,   // your own: props every page gets
    ErrorMiddleware::class,         // your own: exceptions to redirects and error pages
    RoutingMiddleware::class,
];
```

Its constructor takes `Inertia` and a `ResponseFactoryInterface`, so solophp/container autowires it.

The client sends the `XSRF-TOKEN` cookie back in the `X-XSRF-TOKEN` header on every request, so CSRF protection needs no session: hand out a random token in that cookie and refuse a `POST`, `PUT`, `PATCH` or `DELETE` whose header does not match it. The skeleton's `CsrfMiddleware` does so in thirty lines, and sends a refused form back with a "page expired" message.

## 4. A page

A handler renders a page component with its props:

```php
public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    return $this->inertia->render($request, $response, 'Tasks/Index', [
        'tasks' => fn() => $this->tasks->all(),
    ]);
}
```

The first visit gets the HTML document; every visit after it, made by the client with `X-Inertia: true`, gets the page object as JSON.

## 5. A form

A form posts to a handler that redirects. Validation errors go back to the form, and the client shows them from its `errors` prop:

```php
public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $title = trim((string) ($request->getParsedBody()['title'] ?? ''));
    if ($title === '') {
        return $this->inertia->back($request, $response, errors: ['title' => 'The title is required.']);
    }

    $task = $this->tasks->create($title);

    return $this->inertia->redirect($request, $response, "/tasks/$task->id", flash: ['success' => 'Saved.']);
}
```

The client sends JSON bodies: decode them into the parsed body with a middleware, as the skeleton's `JsonBodyMiddleware` does. [Validation](/features/validation) shows how to turn solophp/input's `ValidationException` into this redirect once, in the error middleware.

## 6. The client

With the Vite plugin, the entry point needs one line:

```js
// resources/js/app.js
import { createInertiaApp } from '@inertiajs/svelte'

createInertiaApp()
```

```js
// vite.config.js
import inertia from '@inertiajs/vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'
import { defineConfig } from 'vite'

export default defineConfig({
    plugins: [inertia(), svelte()],
    build: {
        manifest: true,
        outDir: 'public/build',
        rollupOptions: { input: 'resources/js/app.js' },
    },
})
```

Pages live in `resources/js/Pages`: `Tasks/Index` is `resources/js/Pages/Tasks/Index.svelte`.
