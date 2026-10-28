# Root Template

The first visit is a full HTML document: the root template. It loads the assets and holds the element the client mounts on. A `RootViewInterface` renders it; `PhpRootView` does so with a plain PHP file.

## PhpRootView

```php
use Solo\Inertia\PhpRootView;

new PhpRootView(
    __DIR__ . '/resources/views/app.php',
    ['vite' => $vite],   // variables every page gets
);
```

The template gets these variables:

| Variable | |
|---|---|
| `$body` | The markup for `<body>`: the page object's script element and the root element, or the markup of [server-side rendering](/features/ssr). Print it as it is. |
| `$head` | Elements for `<head>` from server-side rendering, `''` without it. Print it as it is. |
| `$page` | The page object as an array, for anything drawn from the props, such as meta tags |
| … | The template data of the constructor, and the `viewData` of `render()`, which wins over it |

```php
<?php /** @var array{props: array<string, mixed>} $page */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'My App') ?></title>
    <?= $vite->tags('resources/js/app.js') ?>
    <?= $head ?>
</head>
<body>
    <?= $body ?>
</body>
</html>
```

`$page`, `$head` and `$body` win over template data of the same name. The template runs in a scope of its own: it sees its variables, not the view object.

## The markup

Without server-side rendering, `$body` is:

```html
<script data-page="app" type="application/json">{"component":"Home","props":{"errors":{}},"url":"\/","version":""}</script><div id="app"></div>
```

The JSON escapes `/` as `\/` and `<`, `>` as `<`, `>`, so no prop can end the script element. The id is `app`; change it with the `rootId` option and the client's `id` option together:

```php
new Inertia($rootView, rootId: 'my-app');
```

```js
createInertiaApp({ id: 'my-app' })
```

## Assets from Vite

With `build.manifest` on, Vite writes `.vite/manifest.json` into its output directory. A template helper turns an entry into tags; the [Svelte skeleton](https://github.com/SoloPHP/Inertia-Svelte-Skeleton) has one in `src/Http/Vite.php`, which also serves the dev server's scripts while `npm run dev` runs:

```php
<?php foreach ($manifest['resources/js/app.js']['css'] ?? [] as $css): ?>
    <link rel="stylesheet" href="/build/<?= $css ?>">
<?php endforeach ?>
<script type="module" src="/build/<?= $manifest['resources/js/app.js']['file'] ?>"></script>
```

The manifest's hash makes a good [asset version](/features/asset-versioning).

## Another template engine

Implement `RootViewInterface` and print `$head` and `$body` unescaped:

```php
use Solo\Inertia\Contracts\RootViewInterface;

final readonly class TwigRootView implements RootViewInterface
{
    public function __construct(private \Twig\Environment $twig)
    {
    }

    public function render(array $page, string $head, string $body, array $viewData): string
    {
        return $this->twig->render('app.html.twig', ['page' => $page, 'head' => $head, 'body' => $body] + $viewData);
    }
}
```
