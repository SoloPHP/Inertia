# Installation

```bash
composer require solophp/inertia
```

## Requirements

| | |
|---|---|
| PHP | 8.3 or later |
| HTTP messages | Any PSR-7 implementation with PSR-17 factories, such as [nyholm/psr7](https://github.com/Nyholm/psr7) |
| Middleware | Any PSR-15 dispatcher, such as [solophp/application](https://solophp.github.io/Application/) |
| Logger | Optional: any PSR-3 logger, for rescued deferred props and failed server-side renders |
| SSR | Optional: any PSR-18 client, for `Ssr\HttpGateway` |

## The client

The package speaks the protocol of **Inertia.js 3**: the first page arrives in a `<script data-page="app" type="application/json">` element, which clients before 3.0 do not read by default. Install the adapter of your front-end framework and, optionally, the Vite plugin:

```bash
npm install @inertiajs/svelte @inertiajs/vite   # or @inertiajs/vue3, @inertiajs/react
```

The [client-side setup](https://inertiajs.com/docs/v3/installation/client-side-setup) of the Inertia documentation applies as it is. Everything it says about the server is done by this package instead of the Laravel adapter; the pages of this guide show the PSR way.

## What is in the package

| Class | Role |
|---|---|
| `Inertia` | Renders pages, makes redirects, creates props with rules of their own |
| `InertiaMiddleware` | Version conflicts, 303 after PUT/PATCH/DELETE, fragment redirects, `Vary` |
| `EncryptHistoryMiddleware` | Encrypts the history state of the pages below it |
| `PrecognitionMiddleware` | Validates a form while the user fills it in, without running the handler |
| `Prop`, `ScrollMetadata` | Props with rules: always, optional, deferred, merge, once, infinite scroll |
| `CookieFlashStore` | Carries errors and flash data to the next page in a signed cookie |
| `PhpRootView` | The HTML document of a first visit, from a PHP template |
| `Ssr\HttpGateway` | Server-side rendering with Inertia's Node SSR server |
| `Header` | The header names of the protocol |

Next: [Quick Start](/guide/quick-start).
