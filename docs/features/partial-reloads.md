# Partial Reloads

A visit to the page the client is on can ask for some of its props only:

```js
router.reload({ only: ['users'] })
router.reload({ except: ['companies'] })
```

The client sends the component in `X-Inertia-Partial-Component` and the paths in `X-Inertia-Partial-Data` or `X-Inertia-Partial-Except`. When the component matches the page being rendered, `render()` resolves only the selected props:

- `only` keeps the listed paths; `except` removes them; with both, `only` narrows first and `except` removes from what is left.
- A path selects a branch: `auth` is all of `auth`, `auth.user` only that key inside it.
- `errors` and [always props](#always-props) are sent whatever the paths say.
- A value resolved as a whole, such as a Closure that returns an array, is sent whole.

When the visit ends on another component — the user was signed out and redirected to the login page — the response is a full one.

## Lazy props

A prop wrapped in a Closure is called only when the response sends it, so a partial reload costs only what it asks for:

```php
return $this->inertia->render($request, $response, 'Users/Index', [
    'users' => fn() => $this->users->filtered($filters),
    'companies' => fn() => $this->companies->all(),
]);
```

| Prop | Full visit | Partial reload | Resolved |
|---|---|---|---|
| `$this->users->all()` | sent | when selected | always |
| `fn() => $this->users->all()` | sent | when selected | when sent |
| `Inertia::optional(fn() => ...)` | never | when selected | when sent |
| `Inertia::always(fn() => ...)` | sent | sent | always |
| `Inertia::defer(fn() => ...)` | [announced](/features/deferred-props) | when selected | when sent |

## Optional props

An optional prop is never part of a full visit, and the client is not told about it: it is sent only when a partial reload selects it.

```php
'roles' => Inertia::optional(fn() => $this->roles->all()),
```

A reload with `except` only selects every prop it does not exclude, optional ones included.

## Always props

An always prop is sent on every response, even a partial reload that did not ask for it:

```php
'flags' => Inertia::always(fn() => $this->features->enabled()),
```

The `errors` prop is one. An always prop cannot be a [once prop](/features/once-props).

## Resetting props

`router.reload({ reset: ['results'] })` sends `X-Inertia-Reset: results`. The prop is resolved again and sent without its [merge](/features/merging-props) label, so the client replaces it; an [infinite scroll](/features/infinite-scroll) prop gets `reset: true` in its metadata.
