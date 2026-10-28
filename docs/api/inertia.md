# Inertia

`Solo\Inertia\Inertia` — renders pages, makes the redirects an Inertia app needs, and creates props with rules of their own. It holds configuration only: one instance serves every request.

## Constructor

```php
public function __construct(
    RootViewInterface $rootView,
    string $version = '',
    ?FlashStoreInterface $flash = null,
    ?SsrGatewayInterface $ssr = null,
    bool $encryptHistory = false,
    ?LoggerInterface $logger = null,
    string $rootId = 'app',
)
```

| Parameter | |
|---|---|
| `$rootView` | The HTML document of a first visit, such as a [`PhpRootView`](/api/root-view#phprootview) |
| `$version` | The [asset version](/features/asset-versioning); `''` when it is not tracked. Public, as `$inertia->version` |
| `$flash` | Carries flash data, errors and history flags across a redirect. Without one, `render()` reads nothing and `redirect()` with any of them throws |
| `$ssr` | Renders first visits on the server; `null` renders on the client |
| `$encryptHistory` | Encrypt the history state of every page |
| `$logger` | Gets the deferred props that failed and were rescued |
| `$rootId` | The id of the root element and of the page's script element; the client's `id` option |

## render()

```php
public function render(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string|BackedEnum $component,
    array $props = [],
    array $flash = [],
    ?bool $encryptHistory = null,
    bool $clearHistory = false,
    array $viewData = [],
): ResponseInterface
```

The page `$component` with `$props`, shared props, and what a redirect left: the HTML document on a first visit, the page object as JSON on an Inertia visit. Writes into `$response`'s body and keeps its status. See [Responses](/features/responses).

| Parameter | |
|---|---|
| `$props` | Values, Closures called only when the prop is sent, [`Prop`](/api/prop) objects; dotted keys nest |
| `$flash` | One-time data for this page, next to what a redirect left |
| `$encryptHistory` | Encrypt this page's history; `null` follows `EncryptHistoryMiddleware` and the constructor |
| `$clearHistory` | Rotate the history encryption key |
| `$viewData` | Template data that never reaches the client |

Throws `InvalidArgumentException` for an empty component name, `LogicException` for a dotted key that cannot nest, and whatever a resolved prop throws, unless it is a rescued deferred prop.

## redirect()

```php
public function redirect(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string $url,
    array $flash = [],
    array $errors = [],
    bool $clearHistory = false,
    bool $preserveFragment = false,
    int $status = 302,
): ResponseInterface
```

A redirect to `$url`. `$flash`, `$errors`, `$clearHistory` and `$preserveFragment` reach the next page Inertia renders, together with what an earlier redirect left and no page has shown. Throws `InvalidArgumentException` for a status outside 3xx, and `LogicException` when there is something to carry and no flash store. See [Redirects](/features/redirects).

## back()

```php
public function back(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string $fallback = '/',
    array $flash = [],
    array $errors = [],
): ResponseInterface
```

A redirect to the path and query string of the `Referer`, when it comes from the request's host, or to `$fallback`.

## location()

```php
public function location(ServerRequestInterface $request, ResponseInterface $response, string $url): ResponseInterface
```

A full page load of `$url`: a `409` with `X-Inertia-Location` on an Inertia visit, a `302` otherwise.

## Shared props

```php
public static function share(ServerRequestInterface $request, array $props): ServerRequestInterface
public static function shared(ServerRequestInterface $request): array
```

`share()` returns the request with `$props` added to the props every page rendered for it gets; `shared()` returns them. See [Shared Data](/features/shared-data).

## Request checks

```php
public static function isInertia(ServerRequestInterface $request): bool
public static function isPrefetch(ServerRequestInterface $request): bool
public static function isPrecognitive(ServerRequestInterface $request): bool
```

`isInertia()`: the request has `X-Inertia`, so the client expects a page object, a redirect or a `409`. `isPrefetch()`: its `Purpose` is `prefetch`. `isPrecognitive()`: its `Precognition` is `true`, so it asks for validation only.

## Prop factories

```php
public static function always(mixed $value): Prop
public static function optional(callable $resolver): Prop
public static function defer(callable $resolver, string $group = 'default', bool $rescue = false): Prop
public static function merge(mixed $value): Prop
public static function deepMerge(mixed $value): Prop
public static function once(callable $resolver): Prop
public static function scroll(mixed $value, ScrollMetadata|callable $metadata, string $wrapper = 'data'): Prop
```

Each returns a [`Prop`](/api/prop); callables become Closures.

## Constants

| Constant | |
|---|---|
| `SHARED_PROPS` | The request attribute `share()` writes: `'solo.inertia.shared_props'` |
| `ENCRYPT_HISTORY` | The request attribute `EncryptHistoryMiddleware` sets to `true`: `'solo.inertia.encrypt_history'` |
