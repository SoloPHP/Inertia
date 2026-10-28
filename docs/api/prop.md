# Prop & ScrollMetadata

## Prop

`Solo\Inertia\Prop` — an immutable prop with its own rules. The [factories on `Inertia`](/api/inertia#prop-factories) create one; each method returns a changed copy.

### Methods

| Method | |
|---|---|
| `defer(string $group = 'default')` | Left out of the first visit and loaded right after it with the group |
| `merge()` | Appended to what the client has, at the root unless paths say otherwise |
| `deepMerge()` | Merged into what the client has at every depth |
| `append(?string $path = null, ?string $matchOn = null)` | Appended at `$path` in the prop, or at its root |
| `prepend(?string $path = null, ?string $matchOn = null)` | Prepended at `$path` in the prop, or at its root |
| `matchOn(string ...$paths)` | The fields, as paths from the prop, that identify an item |
| `once()` | Remembered by the client and skipped while it has it |
| `as(string\|BackedEnum $key)` | A once prop remembered under `$key` |
| `until(DateTimeInterface\|DateInterval\|int $ttl)` | A once prop the client forgets after `$ttl` seconds, an interval or a time |
| `fresh(bool $fresh = true)` | A once prop sent even though the client has it |

### Properties

All public and read-only, as the resolver reads them:

| Property | Type | |
|---|---|---|
| `value` | `mixed` | The value, or a Closure that returns it |
| `always` | `bool` | Sent on every response |
| `optional` | `bool` | Sent only when a partial reload asks for it |
| `group` | `?string` | Deferred into this group |
| `rescue` | `bool` | A deferred prop that throws is logged and left out |
| `merge` | `'append'\|'prepend'\|'deep'\|null` | How the client merges the prop |
| `appendAt`, `prependAt` | `list<string>` | Paths in the prop to append and prepend to |
| `matchOn` | `list<string>` | Paths of the fields that identify items |
| `once` | `bool` | Remembered by the client |
| `key` | `?string` | The once key, the prop's path by default |
| `expiresAt` | `?int` | When the client forgets it, in milliseconds since the epoch |
| `fresh` | `bool` | Sent although the client has it |
| `scroll` | `ScrollMetadata\|Closure\|null` | The infinite scroll cursor, or a Closure that takes the value and returns it |
| `wrapper` | `string` | The key of an infinite scroll prop's items |

### Combinations

The constructor throws `LogicException` for a prop that cannot be:

- always, optional and deferred at once, or any two of them;
- rescued without being deferred;
- always and once;
- given a once key, expiry or `fresh` without being once (the methods make it once);
- an infinite scroll prop with another merge;
- a deep merge with append or prepend paths;
- matched on a key without merging;
- wrapping another `Prop`.

## ScrollMetadata

`Solo\Inertia\ScrollMetadata` — where an [infinite scroll](/features/infinite-scroll) prop is in its list.

```php
public function __construct(
    string $pageName = 'page',
    int|string|null $previousPage = null,
    int|string|null $nextPage = null,
    int|string|null $currentPage = null,
)

public static function forPage(int $page, bool $hasMorePages, string $pageName = 'page'): self
public function toArray(): array
```

`forPage()` is the cursor of a numbered page: no previous page on the first, no next page without more. `toArray()` is the metadata the client gets, without `reset`.
