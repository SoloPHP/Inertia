# Flash Data

Flash data is one-time data for the next page: a success message, the id of the record just created. The client exposes it as `page.flash` and fires the `flash` event; it is not kept in the browser history, so it does not come back with the back button.

```php
return $this->inertia->redirect($request, $response, '/users', flash: [
    'success' => 'User created.',
    'newUserId' => $user->id,
]);
```

```svelte
<script>
    import { page } from '@inertiajs/svelte'
</script>

{#if page.flash.success}
    <div class="toast">{page.flash.success}</div>
{/if}
```

A page can get flash data of its own, without a redirect:

```php
return $this->inertia->render($request, $response, 'Projects/Index', $props, flash: ['highlight' => $project->id]);
```

## How it crosses the redirect

`redirect()` and `back()` hand a `Flash` — data, errors and the two history flags — to the flash store of the `Inertia` service. The next page `render()` makes takes it back, shows it once and lets the store forget it. Until a page is rendered, it stays: a chain of redirects keeps it, and a later redirect adds to it.

- A prefetch request leaves it alone, since a prefetched page may be shown late or never.
- A stale client's `409` leaves it alone too, so the full page load that follows shows it.
- Without a flash store, `render()` reads nothing, and `redirect()` with flash data, errors or history flags throws `LogicException`.

## CookieFlashStore

`CookieFlashStore` keeps the `Flash` in a cookie signed with HMAC-SHA256. It needs no session and no storage on the server, so tests and long-running workers need nothing set up:

```php
use Solo\Inertia\CookieFlashStore;

new Inertia($rootView, flash: new CookieFlashStore($_ENV['APP_KEY']));
```

- The secret must be at least 32 bytes. The cookie is signed with a key derived from it, together with its name, so it cannot be forged or moved to another cookie; a changed cookie reads as empty.
- The data is signed, not encrypted: the user can read it, as they read the page that shows it. Do not flash secrets.
- The cookie is `HttpOnly` and `SameSite=Lax`, and `Secure` on https requests. Options: `name` (`inertia_flash`), `path`, `domain`, `secure` (`null` for "when the request is https") and `sameSite`.
- A browser keeps about 4 KB per cookie. `put()` throws `LengthException` for a `Flash` that does not fit, rather than letting the browser drop it.

## A store of your own

To keep flash data in a session, implement `FlashStoreInterface`. With PHP's native session, started by your session middleware:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Inertia\Contracts\FlashStoreInterface;
use Solo\Inertia\Flash;

final class SessionFlashStore implements FlashStoreInterface
{
    private const string KEY = '_inertia_flash';

    public function get(ServerRequestInterface $request): Flash
    {
        $flash = $_SESSION[self::KEY] ?? null;

        return $flash instanceof Flash ? $flash : new Flash();
    }

    public function put(ServerRequestInterface $request, ResponseInterface $response, Flash $flash): ResponseInterface
    {
        $_SESSION[self::KEY] = $flash;

        return $response;
    }

    public function forget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        unset($_SESSION[self::KEY]);

        return $response;
    }
}
```
