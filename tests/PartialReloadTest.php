<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\PhpRootView;
use Solo\Inertia\Tests\Fixtures\RecordingLogger;

final class PartialReloadTest extends TestCase
{
    /** @return array<array-key, mixed> */
    private static function propsFor(ServerRequestInterface $request, string $component = 'Users'): array
    {
        $page = self::pageOf($request, $component, [
            'users' => fn() => ['ada', 'grace'],
            'companies' => fn() => ['acme'],
            'auth' => [
                'user' => fn() => 'ada',
                'notifications' => fn() => [1, 2],
            ],
            'stats' => fn() => ['visits' => 10, 'sales' => 2],
        ]);

        return self::props($page);
    }

    public function testOnlySendsTheRequestedProps(): void
    {
        self::assertSame(
            ['errors' => [], 'users' => ['ada', 'grace']],
            self::propsFor(self::reload('Users', ['users'])),
        );
    }

    public function testExceptSendsEverythingElse(): void
    {
        self::assertSame(
            ['errors' => [], 'users' => ['ada', 'grace'], 'stats' => ['visits' => 10, 'sales' => 2]],
            self::propsFor(self::reload('Users', except: ['companies', 'auth'])),
        );
    }

    public function testOnlyNarrowsFirstAndExceptRemovesFromIt(): void
    {
        self::assertSame(
            ['errors' => [], 'companies' => ['acme']],
            self::propsFor(self::reload('Users', ['users', 'companies'], ['users'])),
        );
    }

    public function testDottedPathsSelectABranch(): void
    {
        self::assertSame(
            ['errors' => [], 'auth' => ['notifications' => [1, 2]]],
            self::propsFor(self::reload('Users', ['auth.notifications'])),
        );
        self::assertSame(
            ['errors' => [], 'auth' => ['user' => 'ada']],
            self::propsFor(self::reload('Users', ['auth'], ['auth.notifications'])),
        );
    }

    public function testValueResolvedAsAWholeIsSentWhole(): void
    {
        // stats is a Closure: the whole array it returns is sent, whatever path was asked for
        self::assertSame(
            ['errors' => [], 'stats' => ['visits' => 10, 'sales' => 2]],
            self::propsFor(self::reload('Users', ['stats.visits'])),
        );
    }

    public function testAnotherComponentGetsAFullResponse(): void
    {
        // The visit ended on another page, such as a login page: no partial reload happens
        self::assertArrayHasKey('companies', self::propsFor(self::reload('Dashboard', ['users']), 'Users'));
    }

    public function testHeadersOfANonInertiaRequestAreIgnored(): void
    {
        $request = self::visit(headers: [Header::PARTIAL_COMPONENT => 'Users', Header::PARTIAL_DATA => 'users']);

        $page = self::htmlPage(
            self::inertia()->render($request, self::response(), 'Users', ['users' => 1, 'more' => 2]),
        );

        self::assertSame(['errors' => [], 'users' => 1, 'more' => 2], $page['props']);
    }

    public function testAlwaysPropsIgnoreTheFilters(): void
    {
        $page = self::pageOf(self::reload('Users', ['users'], ['flags']), 'Users', [
            'users' => [],
            'flags' => Inertia::always(fn() => ['beta' => true]),
            'other' => 1,
        ]);

        self::assertSame(['errors' => [], 'users' => [], 'flags' => ['beta' => true]], $page['props']);
    }

    public function testOptionalPropsAreSentOnlyWhenAskedFor(): void
    {
        $props = [
            'users' => [],
            'roles' => Inertia::optional(fn() => ['admin']),
        ];

        $full = self::pageOf(self::xhr(), 'Users', $props);
        self::assertSame(['errors' => [], 'users' => []], $full['props']);
        self::assertArrayNotHasKey('deferredProps', $full);

        $reload = self::pageOf(self::reload('Users', ['roles']), 'Users', $props);
        self::assertSame(['errors' => [], 'roles' => ['admin']], $reload['props']);

        // An except-only reload selects every prop it does not exclude, optional ones too
        $except = self::pageOf(self::reload('Users', except: ['users']), 'Users', $props);
        self::assertSame(['errors' => [], 'roles' => ['admin']], $except['props']);
    }

    public function testDeferredPropsAreLoadedByTheirReload(): void
    {
        $props = [
            'permissions' => Inertia::defer(fn() => ['edit']),
            'teams' => Inertia::defer(fn() => ['a'], 'attributes'),
        ];

        $reload = self::pageOf(self::reload('Users', ['teams']), 'Users', $props);

        self::assertSame(['errors' => [], 'teams' => ['a']], $reload['props']);
        self::assertArrayNotHasKey('deferredProps', $reload);
    }

    public function testNestedPropTypesAreResolvedAtAnyDepth(): void
    {
        $props = ['auth' => fn() => [
            'user' => 'ada',
            'notifications' => Inertia::defer(fn() => [1]),
            'invoices' => Inertia::optional(fn() => [2]),
        ]];

        $full = self::pageOf(self::xhr(), 'Dashboard', $props);
        self::assertSame(['errors' => [], 'auth' => ['user' => 'ada']], $full['props']);
        self::assertSame(['default' => ['auth.notifications']], $full['deferredProps']);

        $reload = self::page(self::inertia()->render(
            self::reload('Dashboard', ['auth.notifications']),
            self::response(),
            'Dashboard',
            ['auth' => [
                'user' => 'ada',
                'notifications' => Inertia::defer(fn() => [1]),
            ]],
        ));
        self::assertSame(['errors' => [], 'auth' => ['notifications' => [1]]], $reload['props']);
    }

    public function testClosureMayReturnAPropType(): void
    {
        $page = self::pageOf(self::xhr(), 'Users', [
            'users' => fn() => Inertia::defer(fn() => ['ada']),
        ]);

        self::assertSame(['errors' => []], $page['props']);
        self::assertSame(['default' => ['users']], $page['deferredProps']);
    }

    public function testAFailingDeferredPropFailsTheRequestUnlessRescued(): void
    {
        $this->expectExceptionMessage('Down');

        self::inertia()->render(self::reload('Users', ['permissions']), self::response(), 'Users', [
            'permissions' => Inertia::defer(fn() => throw new RuntimeException('Down')),
        ]);
    }

    public function testARescuedPropIsLogged(): void
    {
        $logger = new RecordingLogger();
        $inertia = new Inertia(new PhpRootView(__DIR__ . '/Fixtures/templates/root.php'), logger: $logger);

        $request = self::reload('Users', ['permissions', 'users']);
        $page = self::page($inertia->render($request, self::response(), 'Users', [
            'users' => ['ada'],
            'permissions' => Inertia::defer(fn() => throw new RuntimeException('Down'), rescue: true),
        ]));

        self::assertSame(['errors' => [], 'users' => ['ada']], $page['props']);
        self::assertSame(['permissions'], $page['rescuedProps']);
        self::assertCount(1, $logger->records);
        $record = $logger->records[0];
        self::assertSame('error', $record['level']);
        self::assertSame('Deferred prop {prop} failed and was left out: {message}', $record['message']);
        self::assertSame('permissions', $record['context']['prop']);
        self::assertInstanceOf(RuntimeException::class, $record['context']['exception']);
    }
}
