<?php

declare(strict_types=1);

namespace Solo\Inertia;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Solo\Inertia\Contracts\FlashStoreInterface;
use Solo\Inertia\Contracts\RootViewInterface;
use Solo\Inertia\Contracts\SsrGatewayInterface;
use Solo\Inertia\Internal\Json;
use Solo\Inertia\Internal\PropsResolver;
use Solo\Inertia\Internal\Vary;
use stdClass;

/**
 * Answers a request with an Inertia page: an HTML document on the first visit, the
 * page object as JSON on the visits the client makes after it. It also makes the
 * redirects an Inertia app needs, and the props with rules of their own.
 *
 * The service holds configuration only. What belongs to a request travels with it:
 * shared props in a request attribute, flash data through the flash store. One
 * instance serves every request, in a long-running worker too.
 */
final readonly class Inertia
{
    /** Request attribute with the props share() added, an array */
    public const string SHARED_PROPS = 'solo.inertia.shared_props';

    /** Request attribute EncryptHistoryMiddleware sets to true */
    public const string ENCRYPT_HISTORY = 'solo.inertia.encrypt_history';

    // For a <script> element: "/" stays escaped and "<", ">" become <, >, so no prop can end it
    private const int SCRIPT_JSON = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR;

    /**
     * @param RootViewInterface        $rootView       The HTML document of a first visit
     * @param string                   $version        The asset version: a client built from other assets reloads
     *                                                 the page; '' when the version is not tracked
     * @param FlashStoreInterface|null $flash          Carries flash data, errors and history flags across a redirect
     * @param SsrGatewayInterface|null $ssr            Renders first visits on the server
     * @param bool                     $encryptHistory Encrypt the history state of every page
     * @param LoggerInterface|null     $logger         Reports the deferred props that failed and were rescued
     * @param string                   $rootId         The id of the root element, the client's `id` option
     */
    public function __construct(
        private RootViewInterface $rootView,
        public string $version = '',
        private ?FlashStoreInterface $flash = null,
        private ?SsrGatewayInterface $ssr = null,
        private bool $encryptHistory = false,
        private ?LoggerInterface $logger = null,
        private string $rootId = 'app',
    ) {
    }

    /**
     * The page $component with $props: an HTML document on a first visit, the page
     * object as JSON on an Inertia visit. Shared props come along, and so do the
     * errors, flash data and history flags a redirect left. The response keeps its
     * status, so an error page is rendered with its own.
     *
     * @param array<array-key, mixed> $props          Values, Closures called only when the prop is sent, and
     *                                                Prop objects; a key with dots, such as "auth.user", nests
     * @param array<string, mixed>    $flash          One-time data for this page, beside what a redirect left
     * @param bool|null               $encryptHistory Encrypt this page's history state; null follows
     *                                                EncryptHistoryMiddleware and the constructor
     * @param bool                    $clearHistory   Rotate the key of the encrypted history
     * @param array<string, mixed>    $viewData       Data for the root template only, never sent to the client
     */
    public function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string|BackedEnum $component,
        array $props = [],
        array $flash = [],
        ?bool $encryptHistory = null,
        bool $clearHistory = false,
        array $viewData = [],
    ): ResponseInterface {
        $component = $component instanceof BackedEnum ? (string) $component->value : $component;
        if ($component === '') {
            throw new InvalidArgumentException('The component name is empty');
        }

        // A prefetched page may be shown late or never: what a redirect left waits for a real visit
        $store = self::isPrefetch($request) ? null : $this->flash;
        $flashed = $store?->get($request) ?? new Flash();

        $shared = array_replace(
            ['errors' => self::always(self::errors($request, $flashed->errors))],
            self::shared($request),
        );
        [$props, $metadata] = (new PropsResolver($request, $component, $this->logger))->resolve($shared, $props);

        $page = [
            'component' => $component,
            'props' => $props,
            'url' => self::url($request),
            'version' => $this->version,
            ...$metadata,
        ];
        if ($clearHistory || $flashed->clearHistory) {
            $page['clearHistory'] = true;
        }
        if ($encryptHistory ?? ($this->encryptHistory || $request->getAttribute(self::ENCRYPT_HISTORY) === true)) {
            $page['encryptHistory'] = true;
        }
        $flash = array_replace($flashed->data, $flash);
        if ($flash !== []) {
            $page['flash'] = (object) $flash;
        }
        if ($flashed->preserveFragment) {
            $page['preserveFragment'] = true;
        }

        if (self::isInertia($request)) {
            $response->getBody()->write(json_encode($page, Json::FLAGS));
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader(Header::INERTIA, 'true');
        } else {
            $response->getBody()->write($this->document($page, $viewData));
            $response = $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        $response = Vary::add($response);

        return $store === null ? $response : $store->forget($request, $response);
    }

    /**
     * A redirect to $url. Flash data, errors and history flags given here reach the
     * next page Inertia renders, through the flash store. InertiaMiddleware turns a
     * 302 after a PUT, PATCH or DELETE into a 303, so the client follows it with a GET.
     *
     * @param array<string, mixed> $flash            One-time data for the next page, its `flash`
     * @param array<string, mixed> $errors           Validation errors for the next page, its `errors` prop
     * @param bool                 $clearHistory     The next page rotates the key of the encrypted history,
     *                                               as after a logout
     * @param bool                 $preserveFragment The next page keeps the URL fragment of the visit that
     *                                               led here
     */
    public function redirect(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $url,
        array $flash = [],
        array $errors = [],
        bool $clearHistory = false,
        bool $preserveFragment = false,
        int $status = 302,
    ): ResponseInterface {
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException("A redirect has the status 301, 302, 303, 307 or 308, $status given");
        }

        $response = $response->withStatus($status)->withHeader('Location', $url);

        $next = new Flash($flash, $errors, $clearHistory, $preserveFragment);
        if ($next->isEmpty()) {
            return $response;
        }
        if ($this->flash === null) {
            throw new LogicException(
                'Flash data, errors and history flags reach the next page through a flash store: pass one to Inertia'
            );
        }

        // What an earlier redirect left and no page has shown yet stays, under what this one adds
        return $this->flash->put($request, $response, $this->flash->get($request)->merge($next));
    }

    /**
     * A redirect to the page the request came from, by its Referer, or to $fallback
     * without one from this host. The usual answer to a form with errors.
     *
     * @param array<string, mixed> $flash  One-time data for the next page, its `flash`
     * @param array<string, mixed> $errors Validation errors for the next page, its `errors` prop
     */
    public function back(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $fallback = '/',
        array $flash = [],
        array $errors = [],
    ): ResponseInterface {
        return $this->redirect($request, $response, self::previousUrl($request) ?? $fallback, $flash, $errors);
    }

    /**
     * A full page load of $url, for a page outside the Inertia app or another site:
     * on an Inertia visit, a 409 with X-Inertia-Location that the client follows with
     * window.location; otherwise a plain redirect.
     */
    public function location(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $url,
    ): ResponseInterface {
        return self::isInertia($request)
            ? $response->withStatus(409)->withHeader(Header::LOCATION, $url)
            : $response->withStatus(302)->withHeader('Location', $url);
    }

    /**
     * The request, with $props shared with every page rendered for it. A middleware
     * shares what every page needs, such as the signed-in user, and hands the request
     * on: `$handler->handle(Inertia::share($request, ['auth.user' => fn() => ...]))`.
     * Props shared later, and a page's own props, win over earlier ones by key.
     *
     * @param array<array-key, mixed> $props Props as render() takes them
     */
    public static function share(ServerRequestInterface $request, array $props): ServerRequestInterface
    {
        return $request->withAttribute(self::SHARED_PROPS, array_replace(self::shared($request), $props));
    }

    /** @return array<array-key, mixed> The props shared with the pages of the request */
    public static function shared(ServerRequestInterface $request): array
    {
        $shared = $request->getAttribute(self::SHARED_PROPS);

        return is_array($shared) ? $shared : [];
    }

    /** Whether the Inertia client made the request, so it expects a page object, a redirect or a 409 */
    public static function isInertia(ServerRequestInterface $request): bool
    {
        return $request->getHeaderLine(Header::INERTIA) !== '';
    }

    /** Whether the client fetched the page ahead of a visit that may not come */
    public static function isPrefetch(ServerRequestInterface $request): bool
    {
        return strcasecmp($request->getHeaderLine(Header::PURPOSE), 'prefetch') === 0;
    }

    /** Whether the request asks for validation only: a form validated while the user fills it in */
    public static function isPrecognitive(ServerRequestInterface $request): bool
    {
        return $request->getHeaderLine(Header::PRECOGNITION) === 'true';
    }

    /** A prop sent on every response, even a partial reload that did not ask for it */
    public static function always(mixed $value): Prop
    {
        return new Prop($value, always: true);
    }

    /** A prop sent only when a partial reload asks for it */
    public static function optional(callable $resolver): Prop
    {
        return new Prop($resolver(...), optional: true);
    }

    /**
     * A prop left out of the first visit, which the client loads right after it, in
     * one request per $group. A rescued one that throws is logged and left out, and
     * the client shows the rescue slot of its <Deferred>, instead of the request failing.
     */
    public static function defer(callable $resolver, string $group = 'default', bool $rescue = false): Prop
    {
        return new Prop($resolver(...), group: $group, rescue: $rescue);
    }

    /** A prop the client appends to what it has on a partial reload, instead of replacing it */
    public static function merge(mixed $value): Prop
    {
        return new Prop($value, merge: 'append');
    }

    /** A prop the client merges into what it has, at every depth, on a partial reload */
    public static function deepMerge(mixed $value): Prop
    {
        return new Prop($value, merge: 'deep');
    }

    /** A prop the client remembers, so the pages after it that have it do not resolve it again */
    public static function once(callable $resolver): Prop
    {
        return new Prop($resolver(...), once: true);
    }

    /**
     * A page of an infinite scroll list: the client's <InfiniteScroll> merges the items
     * under $wrapper and asks for the page before or after by $metadata.
     *
     * @param ScrollMetadata|callable(mixed): ScrollMetadata $metadata The cursor, or a callable that takes the
     *                                                                 resolved value and returns it
     */
    public static function scroll(mixed $value, ScrollMetadata|callable $metadata, string $wrapper = 'data'): Prop
    {
        return new Prop(
            $value,
            scroll: $metadata instanceof ScrollMetadata ? $metadata : $metadata(...),
            wrapper: $wrapper,
        );
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $viewData
     */
    private function document(array $page, array $viewData): string
    {
        $rendered = $this->ssr?->render($page);

        $body = $rendered->body ?? sprintf(
            '<script data-page="%1$s" type="application/json">%2$s</script><div id="%1$s"></div>',
            htmlspecialchars($this->rootId, ENT_QUOTES),
            json_encode($page, self::SCRIPT_JSON),
        );

        return $this->rootView->render($page, $rendered->head ?? '', $body, $viewData);
    }

    /**
     * The errors a redirect left, as an object even when there are none, under the
     * error bag a form named in X-Inertia-Error-Bag.
     *
     * @param array<string, mixed> $errors
     */
    private static function errors(ServerRequestInterface $request, array $errors): stdClass
    {
        if ($errors === []) {
            return new stdClass();
        }

        $bag = $request->getHeaderLine(Header::ERROR_BAG);

        return $bag === '' ? (object) $errors : (object) [$bag => (object) $errors];
    }

    /** The path and query string of the request, the page's url */
    private static function url(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $path = self::rooted($uri->getPath());
        $query = $uri->getQuery();

        return $query === '' ? $path : "$path?$query";
    }

    /** The Referer as a path on this host, or null for one that is missing or from another host */
    private static function previousUrl(ServerRequestInterface $request): ?string
    {
        $referer = $request->getHeaderLine('Referer');
        $parts = $referer === '' ? false : parse_url($referer);
        if ($parts === false) {
            return null;
        }

        $host = $request->getUri()->getHost();
        if (isset($parts['host']) ? $host !== '' && strcasecmp($parts['host'], $host) !== 0 : $referer[0] !== '/') {
            return null;
        }

        // Only the path and query string: the redirect stays on this host, whatever the header says
        $path = self::rooted($parts['path'] ?? '');

        return isset($parts['query']) ? "$path?{$parts['query']}" : $path;
    }

    /**
     * The path with one leading slash: browsers read "//host" and "/\host" as another host
     */
    private static function rooted(string $path): string
    {
        return '/' . ltrim($path, '/\\');
    }
}
