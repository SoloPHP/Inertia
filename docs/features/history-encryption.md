# History Encryption

The client keeps every page's props in the browser history, so after signing out, the back button could show what the user saw before. With history encryption, the client encrypts a page's state with a key it keeps in session storage; clearing the history rotates the key, and old states can no longer be read — the client fetches them from the server instead.

Encryption needs `window.crypto.subtle`, which browsers give to https pages and `localhost` only.

## Encrypting

Every page, from the constructor:

```php
new Inertia($rootView, encryptHistory: true);
```

The pages of a route group, with `EncryptHistoryMiddleware`:

```php
use Solo\Inertia\EncryptHistoryMiddleware;

$r->group('/account', function (RouteCollector $r): void {
    // ...
}, middlewares: [EncryptHistoryMiddleware::class]);

// or on one route
$r->get('/billing', [BillingController::class, 'show'])->middleware(EncryptHistoryMiddleware::class);
```

One page, either way, with `render()`, which wins over both:

```php
return $this->inertia->render($request, $response, 'Account/Billing', $props, encryptHistory: true);
return $this->inertia->render($request, $response, 'Help', $props, encryptHistory: false);
```

The page object carries `"encryptHistory": true` only when it is on.

## Clearing

Signing out is the usual moment to clear the history. As it ends with a redirect, the flag goes with it to the next page:

```php
return $this->inertia->redirect($request, $response, '/login', clearHistory: true);
```

A page can clear it directly too:

```php
return $this->inertia->render($request, $response, 'Goodbye', clearHistory: true);
```

The client can also call `router.clearHistory()`.
