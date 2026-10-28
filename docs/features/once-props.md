# Once Props

A once prop is resolved once and remembered by the client, which sends its key back in `X-Inertia-Except-Once-Props`. While the client has it, the server skips it — the Closure is not even called — and the client reuses its copy:

```php
return $this->inertia->render($request, $response, 'Billing/Plans', [
    'plans' => Inertia::once(fn() => $this->plans->all()),
]);
```

```json
{
    "props": {"errors": {}, "plans": [...]},
    "onceProps": {"plans": {"prop": "plans", "expiresAt": null}}
}
```

The client forgets a once prop on a page without it. A partial reload that asks for it always gets it, and a first visit always sends every once prop.

## Expiry

`until()` sets when the client forgets the prop: in seconds, as an interval or as a time.

```php
'rates' => Inertia::once(fn() => $this->rates->today())->until(3600),
'rates' => Inertia::once(fn() => $this->rates->today())->until(new DateInterval('P1D')),
'rates' => Inertia::once(fn() => $this->rates->today())->until(new DateTimeImmutable('tomorrow')),
```

The expiry is computed when `until()` is called, so build once props per request.

## Refreshing

`fresh()` sends the prop although the client has it, for when the data behind it has changed:

```php
'plans' => Inertia::once(fn() => $this->plans->all())->fresh($this->plans->changedSince($since)),
```

## Keys

A once prop is remembered under its path. `as()` names the key, so pages that call the prop differently share it:

```php
// Team/Index
'memberRoles' => Inertia::once(fn() => $this->roles->all())->as('roles'),

// Team/Invite
'availableRoles' => Inertia::once(fn() => $this->roles->all())->as('roles'),
```

A backed enum works as a key too.

## Combined

`once()` turns any prop but an always prop into a once prop, and `as()`, `until()` and `fresh()` do too:

```php
'permissions' => Inertia::defer(fn() => $this->permissions->all())->once(),
'activity' => Inertia::merge(fn() => $this->activity->recent($user))->once(),
'categories' => Inertia::optional(fn() => $this->categories->all())->until(600),
```

Shared once props are the usual case: see [shared data](/features/shared-data#once).
