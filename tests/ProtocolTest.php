<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\InertiaMiddleware;
use Solo\Inertia\ScrollMetadata;
use Nyholm\Psr7\Factory\Psr17Factory;
use RuntimeException;

/**
 * The requests and responses of https://inertiajs.com/docs/v3/core-concepts/the-protocol
 */
final class ProtocolTest extends TestCase
{
    private const array EVENT = [
        'id' => 80,
        'title' => 'Birthday party',
        'start_date' => '2019-06-02',
        'description' => "Come out and celebrate Jonathan's 36th birthday party!",
    ];

    public function testFirstVisitIsAnHtmlDocumentWithThePageInAScriptElement(): void
    {
        $response = self::inertia('c32b8e4965f418ad16eaebba1d4e960f')->render(
            self::visit('https://example.com/events/80', headers: ['Accept' => 'text/html, application/xhtml+xml']),
            self::response(),
            'Event',
            ['event' => self::EVENT],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getHeaderLine(Header::INERTIA));

        $html = (string) $response->getBody();
        // Every "/" is escaped, so no prop can close the script element; entities are not used
        self::assertStringContainsString('"url":"\/events\/80"', $html);
        self::assertStringContainsString("Jonathan's", $html);
        self::assertStringContainsString('<div id="app"></div>', $html);

        $page = self::htmlPage($response);
        self::assertSame('Event', $page['component']);
        self::assertSame(['errors' => [], 'event' => self::EVENT], $page['props']);
        self::assertSame('/events/80', $page['url']);
        self::assertSame('c32b8e4965f418ad16eaebba1d4e960f', $page['version']);
        self::assertStringContainsString('"props":{"errors":{},"event":{', $html);
    }

    public function testInertiaVisitIsThePageObjectAsJson(): void
    {
        $response = self::inertia('6b16b94d7c51cbe5b1fa42aac98241d5', encryptHistory: true)->render(
            self::xhr('https://example.com/events/80', version: '6b16b94d7c51cbe5b1fa42aac98241d5'),
            self::response(),
            'Event',
            ['event' => self::EVENT],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('X-Inertia', $response->getHeaderLine('Vary'));
        self::assertSame([
            'component' => 'Event',
            'props' => ['errors' => [], 'event' => self::EVENT],
            'url' => '/events/80',
            'version' => '6b16b94d7c51cbe5b1fa42aac98241d5',
            'sharedProps' => ['errors'],
            'encryptHistory' => true,
        ], self::page($response));
        self::assertStringContainsString('"errors":{}', (string) $response->getBody());
    }

    public function testPartialReloadSendsTheRequestedPropsAndErrors(): void
    {
        $response = self::inertia()->render(
            self::reload('Events', only: ['events'], uri: 'https://example.com/events'),
            self::response(),
            'Events',
            [
                'events' => fn() => [['id' => 80]],
                'categories' => fn() => throw new RuntimeException('Not requested, so never resolved'),
            ],
        );

        self::assertSame(['errors' => [], 'events' => [['id' => 80]]], self::props(self::page($response)));
    }

    public function testDeferredPropsAreAnnouncedByGroupOnAFullVisit(): void
    {
        $never = fn() => throw new RuntimeException('Deferred props resolve in the follow-up request');

        $page = self::pageOf(self::xhr('https://example.com/posts'), 'Posts/Index', [
            'user' => ['name' => 'Jonathan'],
            'comments' => Inertia::defer($never),
            'analytics' => Inertia::defer($never),
            'relatedPosts' => Inertia::defer($never, 'sidebar'),
        ]);

        self::assertSame(['errors' => [], 'user' => ['name' => 'Jonathan']], $page['props']);
        self::assertSame(
            ['default' => ['comments', 'analytics'], 'sidebar' => ['relatedPosts']],
            $page['deferredProps'],
        );
    }

    public function testRescuedDeferredPropIsLeftOutAndListed(): void
    {
        $page = self::page(self::inertia()->render(
            self::reload('Users/Index', only: ['permissions']),
            self::response(),
            'Users/Index',
            ['permissions' => Inertia::defer(fn() => throw new RuntimeException('Down'), rescue: true)],
        ));

        self::assertSame(['errors' => []], $page['props']);
        self::assertSame(['permissions'], $page['rescuedProps']);
    }

    public function testMergePropsAreLabeled(): void
    {
        $page = self::pageOf(self::xhr('https://example.com/feed'), 'Feed/Index', [
            'user' => ['name' => 'Jonathan'],
            'posts' => Inertia::merge([['id' => 1, 'title' => 'First Post']])->matchOn('id'),
            'notifications' => Inertia::merge([['id' => 2, 'message' => 'New comment']])->prepend()->matchOn('id'),
            'conversations' => Inertia::deepMerge(['data' => [['id' => 1, 'title' => 'Support Chat']]])
                ->matchOn('data.id'),
        ]);

        self::assertSame(['posts'], $page['mergeProps']);
        self::assertSame(['notifications'], $page['prependProps']);
        self::assertSame(['conversations'], $page['deepMergeProps']);
        self::assertSame(['posts.id', 'notifications.id', 'conversations.data.id'], $page['matchPropsOn']);
    }

    public function testOncePropIsSkippedWhileTheClientHasIt(): void
    {
        $plans = Inertia::once(fn() => [['id' => 1, 'name' => 'Basic'], ['id' => 2, 'name' => 'Pro']]);

        $first = self::pageOf(self::xhr(), 'Billing/Plans', ['plans' => $plans]);
        self::assertSame([['id' => 1, 'name' => 'Basic'], ['id' => 2, 'name' => 'Pro']], self::props($first)['plans']);
        self::assertSame(['plans' => ['prop' => 'plans', 'expiresAt' => null]], $first['onceProps']);

        $next = self::page(self::inertia()->render(
            self::xhr('https://example.com/billing/upgrade', headers: [Header::EXCEPT_ONCE_PROPS => 'plans']),
            self::response(),
            'Billing/Upgrade',
            ['plans' => $plans, 'currentPlan' => ['id' => 1, 'name' => 'Basic']],
        ));
        self::assertSame(['errors' => [], 'currentPlan' => ['id' => 1, 'name' => 'Basic']], $next['props']);
        self::assertSame(['plans' => ['prop' => 'plans', 'expiresAt' => null]], $next['onceProps']);
    }

    public function testInfiniteScrollPropCarriesItsCursor(): void
    {
        $page = self::pageOf(self::xhr('https://example.com/posts?page=1'), 'Posts/Index', [
            'posts' => Inertia::scroll(
                ['data' => [['id' => 1, 'title' => 'First Post'], ['id' => 2, 'title' => 'Second Post']]],
                ScrollMetadata::forPage(1, hasMorePages: true),
            ),
        ]);

        self::assertSame('/posts?page=1', $page['url']);
        self::assertSame(['posts.data'], $page['mergeProps']);
        self::assertSame(['posts' => [
            'pageName' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
            'reset' => false,
        ]], $page['scrollProps']);
    }

    public function testAssetVersionMismatchIsA409ForAFullReload(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('A stale client gets a 409 before any handler runs');
            }
        };

        $request = self::xhr('https://example.com/events/80', version: '6b16b94d7c51cbe5b1fa42aac98241d5');

        $response = (new InertiaMiddleware(self::inertia('2f8a1c9e4b7d0a3f6c5e8b1d4a7f0c2e'), new Psr17Factory()))
            ->process($request, $handler);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://example.com/events/80', $response->getHeaderLine(Header::LOCATION));
        self::assertSame('2f8a1c9e4b7d0a3f6c5e8b1d4a7f0c2e', $response->getHeaderLine(Header::VERSION));
        self::assertFalse($response->hasHeader(Header::INERTIA));
    }
}
