<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Closure;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Solo\Inertia\Inertia;
use Solo\Inertia\Prop;
use Solo\Inertia\ScrollMetadata;

final class PropTest extends TestCase
{
    public function testFactoriesSetTheCategory(): void
    {
        self::assertTrue(Inertia::always(1)->always);
        self::assertTrue(Inertia::optional(fn() => 1)->optional);
        self::assertSame('default', Inertia::defer(fn() => 1)->group);
        self::assertSame('sidebar', Inertia::defer(fn() => 1, 'sidebar')->group);
        self::assertTrue(Inertia::defer(fn() => 1, rescue: true)->rescue);
        self::assertSame('append', Inertia::merge([])->merge);
        self::assertSame('deep', Inertia::deepMerge([])->merge);
        self::assertTrue(Inertia::once(fn() => 1)->once);

        $scroll = Inertia::scroll([], new ScrollMetadata(), 'items');
        self::assertInstanceOf(ScrollMetadata::class, $scroll->scroll);
        self::assertSame('items', $scroll->wrapper);
    }

    public function testCallablesBecomeClosures(): void
    {
        self::assertInstanceOf(Closure::class, Inertia::optional('time')->value);
        self::assertInstanceOf(Closure::class, Inertia::scroll([], fn() => new ScrollMetadata())->scroll);
        // A plain value stays a value, even a string that names a function
        self::assertSame('time', Inertia::always('time')->value);
    }

    public function testModifiersReturnAChangedCopy(): void
    {
        $prop = Inertia::defer(fn() => 1);
        $merged = $prop->merge();

        self::assertNull($prop->merge);
        self::assertSame('append', $merged->merge);
        self::assertSame('default', $merged->group);
    }

    public function testCategoriesCombine(): void
    {
        $prop = Inertia::defer(fn() => [], 'feed')->deepMerge()->matchOn('id')->once()->as('feed')->fresh();

        self::assertSame('feed', $prop->group);
        self::assertSame('deep', $prop->merge);
        self::assertSame(['id'], $prop->matchOn);
        self::assertTrue($prop->once);
        self::assertSame('feed', $prop->key);
        self::assertTrue($prop->fresh);
    }

    public function testMergeKeepsAMergeModeAndPathsOverrideTheRoot(): void
    {
        self::assertSame('deep', Inertia::deepMerge([])->merge()->merge);
        self::assertSame('prepend', Inertia::merge([])->prepend()->merge);
        self::assertSame('append', Inertia::merge([])->prepend()->append()->merge);

        $prop = Inertia::merge([])->append('data')->prepend('meta.notes', matchOn: 'id');
        self::assertSame(['data'], $prop->appendAt);
        self::assertSame(['meta.notes'], $prop->prependAt);
        self::assertSame(['meta.notes.id'], $prop->matchOn);
    }

    public function testOnceModifiersMakeAOnceProp(): void
    {
        self::assertTrue(Inertia::merge([])->once()->once);
        self::assertTrue(Inertia::optional(fn() => 1)->as('key')->once);
        self::assertTrue(Inertia::defer(fn() => 1)->until(60)->once);
        self::assertTrue(Inertia::merge([])->fresh(false)->once);
        self::assertFalse(Inertia::merge([])->fresh(false)->fresh);
    }

    /** @return iterable<string, array{Closure(): mixed, string}> */
    public static function invalidProps(): iterable
    {
        yield 'always and deferred' => [
            fn() => new Prop(1, always: true, group: 'default'),
            'A prop is always, optional or deferred, not more than one of them',
        ];
        yield 'optional and deferred' => [
            fn() => Inertia::optional(fn() => 1)->defer(),
            'A prop is always, optional or deferred, not more than one of them',
        ];
        yield 'rescued but not deferred' => [
            fn() => new Prop(1, rescue: true),
            'Only a deferred prop can be rescued',
        ];
        yield 'always and once' => [
            fn() => Inertia::always(1)->once(),
            'An always prop is sent on every response, so it cannot be a once prop',
        ];
        yield 'once key without once' => [
            fn() => new Prop(1, key: 'k'),
            'A key, an expiry and fresh() are for once props only',
        ];
        yield 'scroll with another merge' => [
            fn() => Inertia::scroll([], new ScrollMetadata())->prepend(),
            'An infinite scroll prop merges at its wrapper; it takes no other merge',
        ];
        yield 'deep merge with paths' => [
            fn() => Inertia::merge([])->append('data')->deepMerge(),
            'A deep merge merges the whole prop; it takes no append or prepend paths',
        ];
        yield 'match without merge' => [
            fn() => Inertia::optional(fn() => [])->matchOn('id'),
            'Only a merge or infinite scroll prop matches items on a key',
        ];
        yield 'prop in a prop' => [
            fn() => Inertia::always(Inertia::merge([])),
            'A prop wraps a value or a Closure, not another Prop: chain its methods instead',
        ];
    }

    /** @param Closure(): mixed $make */
    #[DataProvider('invalidProps')]
    public function testInvalidCombinationsAreRefused(Closure $make, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $make();
    }
}
