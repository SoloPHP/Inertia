# Root View & SSR

## RootViewInterface

`Solo\Inertia\Contracts\RootViewInterface` — the HTML document of a first visit.

```php
public function render(array $page, string $head, string $body, array $viewData): string;
```

| Parameter | |
|---|---|
| `$page` | The page object, as the client gets it |
| `$head` | Elements for `<head>` from server-side rendering, or `''` |
| `$body` | Markup for `<body>`: the page object's script element and the root element, or what server-side rendering returned |
| `$viewData` | The `viewData` of `render()`: for the template only |

Print `$head` and `$body` without escaping.

## PhpRootView

`Solo\Inertia\PhpRootView` — the root template as a PHP file.

```php
public function __construct(string $template, array $data = [])
```

The template sees `$page`, `$head`, `$body`, the view data and `$data`, in that order of precedence, and nothing else — not even the view object. It throws `RuntimeException` when the file is missing; an exception in the template leaves no output buffer behind. See [Root Template](/features/root-template).

## SsrGatewayInterface

`Solo\Inertia\Contracts\SsrGatewayInterface` — renders a page on the server.

```php
public function render(array $page): ?RenderedPage;
```

`null` leaves rendering to the client. A gateway that fails reports why and returns `null` rather than throwing.

## Ssr\HttpGateway

`Solo\Inertia\Ssr\HttpGateway` — renders with Inertia's Node SSR server over a PSR-18 client.

```php
public function __construct(
    ClientInterface $client,
    RequestFactoryInterface $requests,
    StreamFactoryInterface $streams,
    string $url = 'http://127.0.0.1:13714/render',
    ?LoggerInterface $logger = null,
)
```

It posts the page object as JSON to `$url` and expects a `200` with `{"head": [...], "body": "..."}`. Anything else — a connection error, another status, a body without both — is logged as a warning, with the SSR server's own error fields, and returns `null`. See [Server-Side Rendering](/features/ssr).

## Ssr\RenderedPage

`Solo\Inertia\Ssr\RenderedPage` — a page rendered on the server.

```php
public function __construct(public string $head, public string $body)
```
