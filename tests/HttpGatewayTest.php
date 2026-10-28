<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Closure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;
use Solo\Inertia\Ssr\HttpGateway;
use Solo\Inertia\Ssr\RenderedPage;
use Solo\Inertia\Tests\Fixtures\FakeClient;
use Solo\Inertia\Tests\Fixtures\NetworkError;
use Solo\Inertia\Tests\Fixtures\RecordingLogger;

final class HttpGatewayTest extends TestCase
{
    private const array PAGE = ['component' => 'Event', 'props' => ['errors' => []], 'url' => '/events/80'];

    private RecordingLogger $logger;
    private FakeClient $client;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    /** @param Closure(RequestInterface): ResponseInterface $answer */
    private function gateway(Closure $answer): HttpGateway
    {
        $this->client = new FakeClient($answer);
        $factory = new Psr17Factory();

        return new HttpGateway($this->client, $factory, $factory, logger: $this->logger);
    }

    private static function json(int $status, mixed $data): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function testPostsThePageAndReturnsItsMarkup(): void
    {
        $body = '<script data-page="app" type="application/json">{}</script>'
            . '<div data-server-rendered="true" id="app"><h1>Birthday party</h1></div>';
        $gateway = $this->gateway(fn() => self::json(200, [
            'head' => ['<title>Birthday party</title>', '<meta name="description" content="Party">'],
            'body' => $body,
        ]));

        $rendered = $gateway->render(self::PAGE);

        self::assertEquals(new RenderedPage(
            "<title>Birthday party</title>\n<meta name=\"description\" content=\"Party\">",
            $body,
        ), $rendered);

        $request = $this->client->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://127.0.0.1:13714/render', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(self::PAGE, self::decode((string) $request->getBody()));
        self::assertSame([], $this->logger->records);
    }

    public function testFailedRenderIsLoggedAndLeftToTheClient(): void
    {
        $gateway = $this->gateway(fn() => self::json(500, [
            'error' => 'window is not defined',
            'type' => 'browser-api',
            'component' => 'Events/Show',
            'hint' => 'Wrap browser-specific code in a lifecycle hook',
            'sourceLocation' => 'resources/js/Pages/Events/Show.vue:14:3',
        ]));

        self::assertNull($gateway->render(self::PAGE));

        $record = $this->logger->records[0];
        self::assertSame('warning', $record['level']);
        self::assertSame('Event', $record['context']['component']);
        self::assertSame('/events/80', $record['context']['url']);
        self::assertSame('window is not defined', $record['context']['error']);
        self::assertSame('browser-api', $record['context']['type']);
        self::assertSame('resources/js/Pages/Events/Show.vue:14:3', $record['context']['sourceLocation']);
    }

    public function testUnreachableServerIsAConnectionError(): void
    {
        $gateway = $this->gateway(
            fn(RequestInterface $request) => throw new NetworkError($request, 'Connection refused'),
        );

        self::assertNull($gateway->render(self::PAGE));
        self::assertSame('connection', $this->logger->records[0]['context']['type']);
        self::assertSame('Connection refused', $this->logger->records[0]['context']['error']);
    }

    public function testAnswerWithoutMarkupIsAFailure(): void
    {
        self::assertNull($this->gateway(fn() => self::json(200, ['head' => [1], 'body' => '']))->render(self::PAGE));
        self::assertNull($this->gateway(fn() => new Response(200, [], 'not json'))->render(self::PAGE));
        self::assertNull($this->gateway(fn() => new Response(502, [], 'Bad Gateway'))->render(self::PAGE));
        self::assertCount(3, $this->logger->records);
        self::assertSame('The SSR server answered 502', $this->logger->records[2]['context']['error']);
    }

    public function testRenderedPageGoesIntoTheRootTemplate(): void
    {
        $inertia = new Inertia(
            new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'),
            ssr: $this->gateway(fn() => self::json(200, [
                'head' => ['<meta name="ssr" content="yes">'],
                'body' => '<div data-server-rendered="true" id="app">Hello</div>',
            ])),
        );

        $html = (string) $inertia->render(self::visit(), self::response(), 'Home')->getBody();

        self::assertStringContainsString('<meta name="ssr" content="yes">', $html);
        self::assertStringContainsString('<div data-server-rendered="true" id="app">Hello</div>', $html);
        self::assertStringNotContainsString('<div id="app"></div>', $html);
    }

    public function testInertiaVisitIsNotRenderedOnTheServer(): void
    {
        $inertia = new Inertia(
            new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'),
            ssr: $this->gateway(fn() => throw new RuntimeException('Only first visits are rendered on the server')),
        );

        self::assertSame('Home', self::page($inertia->render(self::xhr(), self::response(), 'Home'))['component']);
    }
}
