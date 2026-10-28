# Infinite Scroll

The client's `<InfiniteScroll>` component loads the pages of a list as the user scrolls and merges each into what it shows. The server sends one page of items and the cursor around it:

```php
use Solo\Inertia\Inertia;
use Solo\Inertia\ScrollMetadata;

$page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
$perPage = 20;
$total = $this->posts->count();

return $this->inertia->render($request, $response, 'Posts/Index', [
    'posts' => Inertia::scroll(
        fn() => ['data' => $this->posts->page($page, $perPage)],
        ScrollMetadata::forPage($page, hasMorePages: $page * $perPage < $total),
    ),
]);
```

```json
{
    "props": {"errors": {}, "posts": {"data": [...]}},
    "mergeProps": ["posts.data"],
    "scrollProps": {"posts": {"pageName": "page", "previousPage": null, "nextPage": 2, "currentPage": 1, "reset": false}}
}
```

```svelte
<script>
    import { InfiniteScroll } from '@inertiajs/svelte'

    let { posts } = $props()
</script>

<InfiniteScroll data="posts">
    {#each posts.data as post (post.id)}
        <article>{post.title}</article>
    {/each}
</InfiniteScroll>
```

## The cursor

`ScrollMetadata` says where the page is: the query parameter of the page number, and the previous, next and current pages.

```php
ScrollMetadata::forPage(3, hasMorePages: true);            // page 3 of more: previous 2, next 4
new ScrollMetadata('cursor', previousPage: 'a', nextPage: 'c', currentPage: 'b');
```

When the value is a Closure, the cursor may be one too: it gets the resolved value.

```php
Inertia::scroll(
    fn() => $this->posts->cursorPage($cursor),
    fn(array $result): ScrollMetadata => new ScrollMetadata('cursor', $result['prev'], $result['next'], $cursor),
);
```

## The items

The items are under `data`; the third argument names another key:

```php
Inertia::scroll(fn() => ['items' => $items, 'total' => $total], $metadata, 'items');
```

## Scrolling up

When the client loads the page before, it sends `X-Inertia-Infinite-Scroll-Merge-Intent: prepend`, and the items are labeled to be prepended.

## Reset and defer

A reset — a new search, say — sends the prop unlabeled with `reset: true` in its cursor, so the client starts over. A deferred scroll prop is loaded right after the page, and carries its cursor then:

```php
'posts' => Inertia::scroll(fn() => ['data' => $this->posts->page(1, 20)], $metadata)->defer(),
```
