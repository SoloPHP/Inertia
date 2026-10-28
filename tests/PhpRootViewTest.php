<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use RuntimeException;
use Solo\Inertia\PhpRootView;

final class PhpRootViewTest extends TestCase
{
    public function testTemplateSeesItsVariablesOnly(): void
    {
        $view = new PhpRootView(
            __DIR__ . '/Fixtures/templates/variables.php',
            ['vite' => 'assets', 'page' => 'shadowed'],
        );

        $output = self::decode($view->render(['component' => 'Home'], '<title>x</title>', '<div></div>', [
            'title' => 'Hello',
            'body' => 'shadowed',
        ]));

        self::assertSame(['variables' => ['page', 'head', 'body', 'title', 'vite'], 'this' => false], $output);
    }

    public function testMissingTemplateFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Root template not found: /nowhere.php');

        (new PhpRootView('/nowhere.php'))->render([], '', '', []);
    }

    public function testFailingTemplateLeavesNoOutputBehind(): void
    {
        $level = ob_get_level();

        try {
            (new PhpRootView(__DIR__ . '/Fixtures/templates/throwing.php'))->render([], '', '', []);
            self::fail('The template should have thrown');
        } catch (RuntimeException $e) {
            self::assertSame('The template failed', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
    }
}
