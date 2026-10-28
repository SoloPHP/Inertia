# Shared Data

Data that every page needs — the signed-in user, the application's name, links of the layout — is shared once instead of passed by every handler. A middleware shares it with `Inertia::share()` and hands the request on:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Inertia\Inertia;

final readonly class SharedPropsMiddleware implements MiddlewareInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getAttribute('user_id'); // set by your authentication middleware

        return $handler->handle(Inertia::share($request, [
            'appName' => 'Solo',
            'auth.user' => fn() => $userId === null ? null : $this->users->summary($userId),
        ]));
    }
}
```

`share()` returns a new request with the props in its `Inertia::SHARED_PROPS` attribute. Every page rendered for that request gets them:

- A page's own props win over shared ones with the same key, and props shared later win over earlier ones.
- Shared props follow the same rules as page props: a Closure is called only when sent, and `Prop` objects keep their rules.
- Dotted keys nest, so `auth.user` here and `auth.permissions` in a page end up in one `auth` prop.
- `Inertia::shared($request)` returns what was shared so far.

The page lists the top-level keys of shared props in `sharedProps`, `errors` among them. The client carries them over to the next page during an [instant visit](https://inertiajs.com/docs/v3/the-basics/instant-visits), before the server answers.

## Why a request attribute

The `Inertia` service holds configuration only. Shared props belong to one request, so they travel with it: a long-running worker serves the next request with nothing left over from this one.

Share from middleware listed before the one that turns exceptions into responses, so error pages get the shared props too.

## Once

Shared data that rarely changes, such as a list of countries, can be a [once prop](/features/once-props): the client keeps it, and the server skips it until the client forgets it.

```php
Inertia::share($request, ['countries' => Inertia::once(fn() => $this->countries->all())]);
```

Data that changes at known moments, such as the user, can be shared as a once prop while it holds and as `null` otherwise, so signing out replaces it:

```php
'auth' => $user === null ? null : Inertia::once(fn() => ['user' => ['id' => $user->id, 'name' => $user->name]]),
```
