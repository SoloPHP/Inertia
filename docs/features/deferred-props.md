# Deferred Props

A deferred prop is left out of the first response, so the page shows as soon as possible. The client loads it right after, with a partial reload:

```php
return $this->inertia->render($request, $response, 'Users/Index', [
    'users' => $this->users->all(),
    'permissions' => Inertia::defer(fn() => $this->permissions->all()),
]);
```

The full visit announces it in `deferredProps`, by group:

```json
{
    "component": "Users/Index",
    "props": {"errors": {}, "users": [...]},
    "deferredProps": {"default": ["permissions"]}
}
```

The client shows its `<Deferred>` fallback until the prop arrives:

```svelte
<script>
    import { Deferred } from '@inertiajs/svelte'

    let { permissions } = $props()
</script>

<Deferred data="permissions">
    {#snippet fallback()}
        <div>Loading...</div>
    {/snippet}
    {#each permissions as permission}
        <div>{permission.name}</div>
    {/each}
</Deferred>
```

## Groups

Deferred props of one group are loaded in one request; groups load in parallel. The group is `default` unless named:

```php
'permissions' => Inertia::defer(fn() => $this->permissions->all()),
'teams' => Inertia::defer(fn() => $this->teams->all(), 'attributes'),
'projects' => Inertia::defer(fn() => $this->projects->all(), 'attributes'),
```

## Rescue

A deferred prop that throws fails its request, like any other code. A rescued one is left out instead: the exception goes to the logger, the prop's path to `rescuedProps`, and the client shows the `rescue` snippet of its `<Deferred>`:

```php
'permissions' => Inertia::defer(fn() => $this->permissions->all(), rescue: true),
```

```php
new Inertia($rootView, logger: $logger); // the logger rescued props are reported to
```

The response is still a 200 with every other prop.

## Nested

A deferred prop may sit anywhere in the props; its path in `deferredProps` has dots:

```php
'auth' => fn() => [
    'user' => $user,
    'notifications' => Inertia::defer(fn() => $this->notifications->unread($user)),
],
// "deferredProps": {"default": ["auth.notifications"]}
```

## Combined

A deferred prop can be [merged](/features/merging-props) and [remembered](/features/once-props):

```php
'results' => Inertia::defer(fn() => $this->search($query))->deepMerge(),
'stats' => Inertia::defer(fn() => $this->stats->generate())->once(),
```

A deferred once prop the client has is neither sent nor announced; the client keeps its copy.
