# Changelog

## 2.0.0 — 2026-10-28

2.0 implements the protocol of Inertia.js 3 and moves the package to the layout of the other Solo packages. See the [upgrade guide](https://solophp.github.io/Inertia/guide/upgrading).

### Breaking changes

- Every class is under `Solo\Inertia\`, autoloaded from `src/`: the service is `Solo\Inertia\Inertia`, no longer `Solo\Inertia`.
- The first page is a `<script data-page="app" type="application/json">` element, as Inertia 3 reads it, instead of a `data-page` attribute: the package needs an Inertia 3 client.
- `Inertia` takes a root view and named options — `version`, `flash`, `ssr`, `encryptHistory`, `logger`, `rootId` — instead of a template path, a version and one JavaScript and one CSS path. The root template gets `$head`, `$body` and `$page`, plus its template data and the view data of `render()`.
- `InertiaMiddleware` takes the `Inertia` service and a PSR-17 response factory. A GET from a client with another asset version gets a `409` with `X-Inertia-Location` before any handler runs; it no longer adds `X-Inertia: true` to every response, and the responses vary by `X-Inertia` instead of `Accept`.
- Props are shared with `Inertia::share()` instead of the `sharedProps` request attribute.
- A Closure prop is called only when the response sends it, and partial reloads select props by path.
- PHP 8.3 or later; `psr/http-factory` and `psr/log` are required, `psr/container` is no longer.

### New

- Partial reloads with `X-Inertia-Partial-Data` and `X-Inertia-Partial-Except` by dotted path, `X-Inertia-Reset`, and dotted keys that nest props.
- Props with rules, combinable and resolvable at any depth: `Inertia::always()`, `optional()`, `defer()` (groups, rescue), `merge()`, `deepMerge()` (append and prepend paths, `matchOn()`), `once()` (`as()`, `until()`, `fresh()`) and `scroll()` with `ScrollMetadata`.
- The page's metadata: `sharedProps`, `deferredProps`, `rescuedProps`, `mergeProps`, `prependProps`, `deepMergeProps`, `matchPropsOn`, `onceProps`, `scrollProps`.
- `redirect()`, `back()` and `location()`: flash data, validation errors, `clearHistory` and `preserveFragment` for the next page, `409` external redirects, and redirects with a fragment answered with `X-Inertia-Redirect` by the middleware.
- An `errors` prop on every page, `{}` without errors, under the error bag of `X-Inertia-Error-Bag`; one-time `flash` data.
- `CookieFlashStore`, which carries errors and flash data to the next page in a signed cookie, without a session; `FlashStoreInterface` for other stores.
- History encryption: the `encryptHistory` option, `EncryptHistoryMiddleware`, and `clearHistory`.
- Precognition, the live validation of forms: `PrecognitionMiddleware` answers `Precognition: true` requests with a `204` or a `422` without running the handler, through a `RequestValidatorInterface` of the application.
- Server-side rendering through `SsrGatewayInterface`, with `Ssr\HttpGateway` for Inertia's Node SSR server over any PSR-18 client.
- `PhpRootView`, `RootViewInterface`, the `Header` constants, `Inertia::isInertia()`, `isPrefetch()` and `isPrecognitive()`.
- Tests, PHPStan at level max, PSR-12, and a [documentation site](https://solophp.github.io/Inertia/).

Earlier releases are described in [GitHub Releases](https://github.com/SoloPHP/Inertia/releases).
