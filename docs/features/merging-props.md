# Merging Props

A prop the client already has is replaced by the new value of a reload. A merge prop is combined with it instead — arrays appended or prepended, objects merged — for "load more" lists and chat logs. Merging applies to partial reloads; a full visit always replaces the prop.

## Append and prepend

```php
// Append to the array the client has
'tags' => Inertia::merge($tags),

// Prepend instead
'notifications' => Inertia::merge($notifications)->prepend(),
```

A path merges inside the prop and replaces the rest of it:

```php
// Append to "data", replace "meta"
'users' => Inertia::merge(['data' => $users, 'meta' => $meta])->append('data'),

// Several paths at once
'forum' => Inertia::merge($forum)->append('posts')->prepend('announcements'),
```

The page labels each path:

```json
{
    "mergeProps": ["users.data", "forum.posts"],
    "prependProps": ["forum.announcements"]
}
```

## Matching items

The client replaces an item that has the same key instead of adding it twice:

```php
'posts' => Inertia::merge($posts)->matchOn('id'),
'users' => Inertia::merge($page)->append('data', matchOn: 'id'),   // "users.data.id"
```

`matchOn()` takes paths from the prop, such as `id` or `data.id`, and replaces the ones given before.

## Deep merge

A deep merge merges the whole structure at every depth:

```php
'chat' => Inertia::deepMerge(['messages' => $messages, 'online' => 12])->matchOn('messages.id'),
```

A deep merge takes no `append()` or `prepend()` paths.

## Combined

Merging combines with the other categories:

```php
'results' => Inertia::defer(fn() => $this->search($query))->merge(),
'activity' => Inertia::merge(fn() => $this->activity->recent($user))->once(),
```

## Resetting

`router.reload({ reset: ['results'] })` sends `X-Inertia-Reset`: the prop is sent without its labels, so the client replaces it, as for a new search.

## Inside a partial reload

A partial reload labels the merge props its paths select, such as `posts` for `only: ['posts']`; other merge props it sends, such as an always prop, are replaced.
