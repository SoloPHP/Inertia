# Server-Side Rendering

By default the first visit arrives with an empty root element, and the client renders the page. With server-side rendering, the server sends the page already rendered, and the client hydrates it: faster first paint, and pages that read without JavaScript.

The rendering is done by Inertia's Node SSR server. `Ssr\HttpGateway` posts the page object to it over any PSR-18 client:

```php
use Solo\Inertia\Ssr\HttpGateway;

new Inertia($rootView, ssr: new HttpGateway(
    $httpClient,        // Psr\Http\Client\ClientInterface
    $requestFactory,    // Psr\Http\Message\RequestFactoryInterface
    $streamFactory,     // Psr\Http\Message\StreamFactoryInterface
    url: 'http://127.0.0.1:13714/render',
    logger: $logger,
));
```

```bash
composer require psr/http-client   # with an implementation, such as solophp/http-client or guzzlehttp/guzzle
```

Only first visits are rendered on the server; Inertia visits get the page object as always. The gateway's `head` elements fill `$head` in the [root template](/features/root-template), and its `body` — the page object's script element and the rendered root element — replaces `$body`.

## The SSR server

Follow the [SSR guide](https://inertiajs.com/docs/v3/advanced/server-side-rendering) of the Inertia documentation for the client side: with the `@inertiajs/vite` plugin, `vite build --ssr` builds the server bundle, and running it with `node` starts the server on port 13714. In development, the Vite dev server answers render requests itself at `/__inertia_ssr`:

```php
url: $isDev ? 'http://localhost:5173/__inertia_ssr' : 'http://127.0.0.1:13714/render',
```

## Failures

A render that fails never fails the request: the gateway logs a warning and returns `null`, and the page is rendered on the client, as without SSR. The log context has the component and URL, and what the SSR server says about the error — `error`, `type` (`browser-api`, `component-resolution`, `render`, or `connection` when the server could not be reached), `hint`, `sourceLocation`.

## A gateway of your own

`SsrGatewayInterface` has one method, which returns the rendered markup or `null`:

```php
use Solo\Inertia\Contracts\SsrGatewayInterface;
use Solo\Inertia\Ssr\RenderedPage;

final readonly class NoSsrOnAdminPages implements SsrGatewayInterface
{
    public function __construct(private SsrGatewayInterface $inner)
    {
    }

    public function render(array $page): ?RenderedPage
    {
        return str_starts_with((string) $page['component'], 'Admin/') ? null : $this->inner->render($page);
    }
}
```
