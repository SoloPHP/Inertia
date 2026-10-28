# Flash & Stores

## Flash

`Solo\Inertia\Flash` — what one response leaves for the next page Inertia renders.

```php
public function __construct(
    array $data = [],              // array<string, mixed>: the page's flash
    array $errors = [],            // array<string, mixed>: the page's errors prop
    bool $clearHistory = false,
    bool $preserveFragment = false,
)

public function isEmpty(): bool
public function merge(self $later): self
```

`merge()` is this one with what a later response adds: its data over this data, its errors instead of these when it has any, and either one's flags.

## FlashStoreInterface

`Solo\Inertia\Contracts\FlashStoreInterface` — carries a `Flash` from the response that redirects to the request that renders the next page.

```php
public function get(ServerRequestInterface $request): Flash;
public function put(ServerRequestInterface $request, ResponseInterface $response, Flash $flash): ResponseInterface;
public function forget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
```

| Method | |
|---|---|
| `get()` | What an earlier response left for this request, or an empty `Flash` |
| `put()` | The response, made to leave `$flash` for the next request; an empty `Flash` leaves nothing |
| `forget()` | The response, made to drop what `get()` returned, once a page has shown it |

`render()` calls `get()` and `forget()` on every request but prefetches; `redirect()` and `back()` call `get()` and `put()` when they have something to carry. A store that keeps data on the server returns the responses unchanged.

## CookieFlashStore

`Solo\Inertia\CookieFlashStore` — keeps the `Flash` in a cookie signed with HMAC-SHA256.

```php
public function __construct(
    #[SensitiveParameter] string $secret,
    string $name = 'inertia_flash',
    string $path = '/',
    ?string $domain = null,
    ?bool $secure = null,
    string $sameSite = 'Lax',
)
```

| Parameter | |
|---|---|
| `$secret` | At least 32 bytes, such as the application's `APP_KEY`; a key derived from it signs the cookie |
| `$name` | Letters, digits, `_` and `-`, which PHP's cookie parsing keeps as they are |
| `$secure` | `true` or `false`; `null` sets `Secure` on https requests |
| `$sameSite` | `Lax`, `Strict`, or `None` with `secure: true` |

The cookie is `HttpOnly`, lasts for the browser session, and is expired by `forget()` when the request sent it. A cookie that was changed, signed with another secret or meant for another name reads as an empty `Flash`. `put()` throws `LengthException` for a cookie over 4096 bytes. The constructor throws `InvalidArgumentException` for a short secret, a name with other characters, or `SameSite=None` without `Secure`.
