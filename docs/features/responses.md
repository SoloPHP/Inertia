# Responses

`render()` answers a request with a page component and its props:

```php
return $this->inertia->render($request, $response, 'Event/Show', [
    'event' => ['id' => 80, 'title' => 'Birthday party'],
]);
```

It writes into the response it is given and returns it:

- **A first visit** — a browser request without `X-Inertia` — gets the HTML document of the [root template](/features/root-template), `Content-Type: text/html; charset=utf-8`.
- **An Inertia visit** gets the page object as JSON, with `Content-Type: application/json` and `X-Inertia: true`.

Both vary by `X-Inertia`. The status stays the response's own, so an error page is a page with a 404 or a 500:

```php
return $this->inertia->render($request, $response->withStatus(404), 'Error', ['status' => 404]);
```

## The page object

```json
{
    "component": "Event/Show",
    "props": {"errors": {}, "event": {"id": 80, "title": "Birthday party"}},
    "url": "/events/80",
    "version": "c32b8e4965f418ad16eaebba1d4e960f",
    "sharedProps": ["errors"]
}
```

- `url` is the path and query string of the request.
- `version` is the [asset version](/features/asset-versioning) of the `Inertia` service.
- `errors` is in every page, `{}` without errors: see [validation](/features/validation).
- Metadata such as `deferredProps`, `mergeProps`, `onceProps`, `flash`, `encryptHistory` appears only when a prop or an option needs it.

## Props

A prop is any value `json_encode()` takes, and three more things:

| Value | Sent |
|---|---|
| A value | As it is |
| A `Closure` | Its return value, called only when the response sends the prop |
| A `JsonSerializable` | What `jsonSerialize()` returns, resolved like any other value |
| A [`Prop`](/api/prop) | By its own rules: always, optional, deferred, merged, once, infinite scroll |

Closures and `Prop` objects are resolved at any depth, and a Closure may return a `Prop`:

```php
return $this->inertia->render($request, $response, 'Dashboard', [
    'user' => $user,                                     // always sent
    'teams' => fn() => $this->teams->of($user),          // sent when asked for, resolved then
    'auth' => [
        'notifications' => Inertia::defer(fn() => $this->notifications->unread($user)),
    ],
]);
```

Only a `Closure` is called: a string such as `'date'` is a string, even though it names a function.

### Dotted keys

A top-level key with dots nests into the prop it names, so a page can add to a shared prop:

```php
Inertia::share($request, ['auth' => fn() => ['user' => $user]]);

return $this->inertia->render($request, $response, 'Account', [
    'auth.permissions' => ['edit'],   // props.auth = {user: ..., permissions: ["edit"]}
]);
```

A Closure on the way is called so the key can nest into what it returns; a value that is not an array there throws `LogicException`.

## The component

The component is the page's name in the client, such as `Tasks/Index` for `Pages/Tasks/Index.svelte`. A string-backed enum works too:

```php
enum Page: string
{
    case TasksIndex = 'Tasks/Index';
}

return $this->inertia->render($request, $response, Page::TasksIndex, $props);
```

## Options

```php
return $this->inertia->render($request, $response, 'Projects/Index', $props,
    flash: ['highlight' => $project->id], // one-time data for this page
    encryptHistory: true,                 // encrypt this page's history state
    clearHistory: true,                   // rotate the history encryption key
    viewData: ['title' => 'Projects'],    // for the root template only
);
```

- `flash` is this page's [flash data](/features/flash-data), next to what a redirect left.
- `encryptHistory` and `clearHistory` are the [history](/features/history-encryption) flags.
- `viewData` reaches the root template and never the client: meta tags, a CSP nonce.
