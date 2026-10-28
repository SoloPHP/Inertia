<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Solo\Inertia\Flash;
use Solo\Inertia\ScrollMetadata;

final class ValueObjectsTest extends TestCase
{
    public function testFlashMerge(): void
    {
        $earlier = new Flash(['message' => 'Signed in', 'level' => 'info'], ['email' => 'Taken'], clearHistory: true);

        $merged = $earlier->merge(new Flash(['level' => 'success'], preserveFragment: true));
        self::assertEquals(
            new Flash(['message' => 'Signed in', 'level' => 'success'], ['email' => 'Taken'], true, true),
            $merged,
        );

        // New errors replace the earlier ones: they are about the latest submission
        self::assertSame(['title' => 'Required'], $earlier->merge(new Flash(errors: ['title' => 'Required']))->errors);
    }

    public function testFlashIsEmpty(): void
    {
        self::assertTrue((new Flash())->isEmpty());
        self::assertFalse((new Flash(clearHistory: true))->isEmpty());
        self::assertFalse((new Flash(errors: ['a' => 'b']))->isEmpty());
    }

    public function testScrollMetadataForAPage(): void
    {
        self::assertEquals(new ScrollMetadata('page', null, 2, 1), ScrollMetadata::forPage(1, hasMorePages: true));
        self::assertEquals(new ScrollMetadata('p', 2, null, 3), ScrollMetadata::forPage(3, false, 'p'));
        self::assertSame(
            ['pageName' => 'cursor', 'previousPage' => 'a', 'nextPage' => 'c', 'currentPage' => 'b'],
            (new ScrollMetadata('cursor', 'a', 'c', 'b'))->toArray(),
        );
    }
}
