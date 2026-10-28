<?php

declare(strict_types=1);

namespace Solo\Inertia\Ssr;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Solo\Inertia\Contracts\SsrGatewayInterface;
use Solo\Inertia\Internal\Json;

/**
 * Renders pages with Inertia's Node SSR server, over any PSR-18 client: it posts the
 * page object to $url and takes back the head elements and the body markup.
 *
 * A render that fails never fails the request. The server's error, with its type,
 * hint and source location when it gives them, goes to the logger, and the page is
 * rendered on the client instead.
 */
final readonly class HttpGateway implements SsrGatewayInterface
{
    /**
     * @param string $url The render endpoint: the SSR server's /render, on port 13714 by default, or in
     *                    development the /__inertia_ssr endpoint @inertiajs/vite adds to the Vite dev server
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
        private string $url = 'http://127.0.0.1:13714/render',
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function render(array $page): ?RenderedPage
    {
        try {
            $request = $this->requests->createRequest('POST', $this->url)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json')
                ->withBody($this->streams->createStream(json_encode($page, Json::FLAGS)));
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            return $this->failed($page, ['error' => $e->getMessage(), 'type' => 'connection']);
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if ($response->getStatusCode() !== 200) {
            return $this->failed($page, is_array($data) ? $data : [
                'error' => 'The SSR server answered ' . $response->getStatusCode(),
            ]);
        }

        $head = is_array($data) ? $data['head'] ?? [] : null;
        $body = is_array($data) ? $data['body'] ?? null : null;
        if (!is_array($head) || !is_string($body) || array_filter($head, is_string(...)) !== $head) {
            return $this->failed($page, ['error' => 'The SSR server answered without head and body']);
        }

        return new RenderedPage(implode("\n", $head), $body);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<array-key, mixed> $error The SSR server's error: error, type, hint, component, sourceLocation...
     */
    private function failed(array $page, array $error): null
    {
        $this->logger?->warning('Server-side rendering of {component} failed, rendering on the client: {error}', [
            'component' => $page['component'] ?? null,
            'url' => $page['url'] ?? null,
            ...array_filter(
                $error,
                static fn(mixed $value, int|string $key): bool => is_string($key) && $key !== 'component',
                ARRAY_FILTER_USE_BOTH,
            ),
            'error' => is_string($error['error'] ?? null) ? $error['error'] : 'unknown error',
        ]);

        return null;
    }
}
