<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use DateInterval;
use DateTimeImmutable;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\ScrollMetadata;
use Solo\Inertia\Tests\Fixtures\Page;
use UnexpectedValueException;

final class MetadataTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $props
     * @return array<string, mixed>
     */
    private static function render(ServerRequestInterface $request, array $props, string $component = 'Feed'): array
    {
        return self::pageOf($request, $component, $props);
    }

    public function testMergeAtPathsWithMatching(): void
    {
        $page = self::render(self::xhr(), [
            'users' => Inertia::merge(['data' => [], 'meta' => []])->append('data', matchOn: 'id'),
            'chat' => Inertia::merge(['posts' => [], 'announcements' => []])->append('posts')->prepend('announcements'),
            'log' => Inertia::merge(['messages' => []])->prepend('messages', matchOn: 'uuid'),
        ]);

        self::assertSame(['users.data', 'chat.posts'], $page['mergeProps']);
        self::assertSame(['chat.announcements', 'log.messages'], $page['prependProps']);
        self::assertSame(['users.data.id', 'log.messages.uuid'], $page['matchPropsOn']);
    }

    public function testDeferredMergeProp(): void
    {
        $results = Inertia::defer(fn() => ['data' => [1]])->deepMerge();

        $full = self::render(self::xhr(), ['results' => $results]);
        self::assertSame(['default' => ['results']], $full['deferredProps']);
        self::assertSame(['results'], $full['deepMergeProps']);

        $reload = self::render(self::reload('Feed', ['results']), ['results' => $results]);
        self::assertSame(['data' => [1]], self::props($reload)['results']);
        self::assertSame(['results'], $reload['deepMergeProps']);
    }

    public function testPartialReloadLabelsOnlyThePropsItAskedFor(): void
    {
        $props = [
            'posts' => Inertia::merge([1])->matchOn('id'),
            'tags' => Inertia::merge(['a']),
            'flags' => Inertia::always(['beta' => true]),
        ];

        $page = self::render(self::reload('Feed', ['posts']), $props);

        self::assertSame(['posts'], $page['mergeProps']);
        self::assertSame(['posts.id'], $page['matchPropsOn']);
    }

    public function testResetPropIsSentUnlabeled(): void
    {
        $page = self::render(self::reload('Feed', ['posts'], headers: [Header::RESET => 'posts']), [
            'posts' => Inertia::merge([1, 2]),
        ]);

        self::assertSame([1, 2], self::props($page)['posts']);
        self::assertArrayNotHasKey('mergeProps', $page);
    }

    public function testOncePropKeysExpiryAndRefresh(): void
    {
        $tomorrow = new DateTimeImmutable('2030-01-02 00:00:00 UTC');
        $props = [
            'memberRoles' => Inertia::once(fn() => ['admin'])->as('roles'),
            'rates' => Inertia::once(fn() => [1.1])->until($tomorrow),
            'plans' => Inertia::once(fn() => ['pro'])->fresh(),
        ];

        $page = self::render(self::xhr(headers: [Header::EXCEPT_ONCE_PROPS => 'roles,rates,plans']), $props);

        // The client has roles and rates; plans is fresh, so it is sent all the same
        self::assertSame(['errors' => [], 'plans' => ['pro']], $page['props']);
        self::assertSame([
            'roles' => ['prop' => 'memberRoles', 'expiresAt' => null],
            'rates' => ['prop' => 'rates', 'expiresAt' => $tomorrow->getTimestamp() * 1000],
            'plans' => ['prop' => 'plans', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testOncePropExpiryInSecondsOrAsAnInterval(): void
    {
        $before = time();
        $page = self::render(self::xhr(), [
            'a' => Inertia::once(fn() => 1)->until(60),
            'b' => Inertia::once(fn() => 2)->until(new DateInterval('PT1H')),
        ]);
        $after = time();

        $once = $page['onceProps'];
        self::assertIsArray($once);
        self::assertIsArray($once['a']);
        self::assertIsArray($once['b']);
        self::assertGreaterThanOrEqual(($before + 60) * 1000, $once['a']['expiresAt']);
        self::assertLessThanOrEqual(($after + 60) * 1000, $once['a']['expiresAt']);
        self::assertGreaterThanOrEqual(($before + 3600) * 1000, $once['b']['expiresAt']);
        self::assertLessThanOrEqual(($after + 3600) * 1000, $once['b']['expiresAt']);
    }

    public function testOncePropAsAnEnumKey(): void
    {
        $page = self::render(self::xhr(), ['a' => Inertia::once(fn() => 1)->as(Page::UsersIndex)]);

        self::assertSame(['Users/Index' => ['prop' => 'a', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testOncePropIsResolvedOnAPartialReloadEvenIfTheClientHasIt(): void
    {
        $page = self::render(
            self::reload('Billing', ['plans'], headers: [Header::EXCEPT_ONCE_PROPS => 'plans']),
            ['plans' => Inertia::once(fn() => ['pro']), 'other' => 1],
            'Billing',
        );

        self::assertSame(['errors' => [], 'plans' => ['pro']], $page['props']);
        self::assertSame(['plans' => ['prop' => 'plans', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testDeferredOncePropTheClientHasIsNotAnnounced(): void
    {
        $props = ['permissions' => Inertia::defer(fn() => ['edit'])->once()];

        $first = self::render(self::xhr(), $props);
        self::assertSame(['default' => ['permissions']], $first['deferredProps']);
        self::assertSame(['permissions' => ['prop' => 'permissions', 'expiresAt' => null]], $first['onceProps']);

        $later = self::render(self::xhr(headers: [Header::EXCEPT_ONCE_PROPS => 'permissions']), $props);
        self::assertArrayNotHasKey('deferredProps', $later);
        self::assertSame(['permissions' => ['prop' => 'permissions', 'expiresAt' => null]], $later['onceProps']);
    }

    public function testOncePropOnAFirstVisitIsSentEvenIfTheHeaderSaysOtherwise(): void
    {
        // Only the Inertia client sends the header; a first visit always gets every once prop
        $page = self::htmlPage(self::inertia()->render(
            self::visit(headers: [Header::EXCEPT_ONCE_PROPS => 'plans']),
            self::response(),
            'Billing',
            ['plans' => Inertia::once(fn() => ['pro'])],
        ));

        self::assertSame(['pro'], self::props($page)['plans']);
    }

    public function testScrollPropPrependsByIntentAndResets(): void
    {
        $posts = Inertia::scroll(
            fn() => ['items' => [3, 4], 'page' => 2],
            fn(mixed $value): ScrollMetadata => ScrollMetadata::forPage(
                is_array($value) && is_int($value['page'] ?? null) ? $value['page'] : 1,
                hasMorePages: false,
                pageName: 'p',
            ),
            wrapper: 'items',
        );

        $prepend = self::render(
            self::reload('Feed', ['posts'], headers: [Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend']),
            ['posts' => $posts],
        );
        self::assertSame(['posts.items'], $prepend['prependProps']);
        self::assertSame(['posts' => [
            'pageName' => 'p',
            'previousPage' => 1,
            'nextPage' => null,
            'currentPage' => 2,
            'reset' => false,
        ]], $prepend['scrollProps']);

        $request = self::reload('Feed', ['posts'], headers: [Header::RESET => 'posts']);
        $reset = self::render($request, ['posts' => $posts]);
        self::assertArrayNotHasKey('mergeProps', $reset);
        $scroll = $reset['scrollProps'];
        self::assertIsArray($scroll);
        self::assertIsArray($scroll['posts']);
        self::assertTrue($scroll['posts']['reset']);
    }

    public function testDeferredScrollPropHasNoCursorOnTheFullVisit(): void
    {
        $posts = Inertia::scroll(fn() => ['data' => [1]], ScrollMetadata::forPage(1, true))->defer();

        $page = self::render(self::xhr(), ['posts' => $posts]);

        self::assertSame(['default' => ['posts']], $page['deferredProps']);
        self::assertSame(['posts.data'], $page['mergeProps']);
        self::assertArrayNotHasKey('scrollProps', $page);
    }

    public function testScrollMetadataCallbackMustReturnScrollMetadata(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The scroll metadata of prop posts must be a ' . ScrollMetadata::class);

        // @phpstan-ignore argument.type (the wrong return type is the point)
        self::render(self::xhr(), ['posts' => Inertia::scroll(['data' => []], fn(mixed $value) => ['page' => 1])]);
    }
}
