<?php

declare(strict_types=1);

namespace Solo\Inertia;

use RuntimeException;
use Solo\Inertia\Contracts\RootViewInterface;
use Throwable;

/**
 * The root template as a plain PHP file. It gets these variables:
 *
 * - `$head`: elements for <head> from server-side rendering, '' without it;
 * - `$body`: the markup for <body>, printed as it is;
 * - `$page`: the page object, for meta tags drawn from props;
 * - the template data given here, and the view data of the render() call.
 *
 * ```php
 * <!doctype html>
 * <html lang="en">
 * <head>
 *     <meta charset="utf-8">
 *     <?= $head ?>
 * </head>
 * <body>
 *     <?= $body ?>
 * </body>
 * </html>
 * ```
 */
final readonly class PhpRootView implements RootViewInterface
{
    /**
     * @param string               $template The template file
     * @param array<string, mixed> $data     Variables every page gets, such as an asset helper
     */
    public function __construct(
        private string $template,
        private array $data = [],
    ) {
    }

    public function render(array $page, string $head, string $body, array $viewData): string
    {
        if (!is_file($this->template)) {
            throw new RuntimeException("Root template not found: $this->template");
        }

        // $page, $head and $body win over data of the same name
        $variables = ['page' => $page, 'head' => $head, 'body' => $body] + $viewData + $this->data;

        $level = ob_get_level();
        ob_start();
        try {
            // Static and without parameters: the template sees its variables only, not this object
            $include = static function (): void {
                extract(func_get_arg(1)); // @phpstan-ignore argument.type (the array passed below)
                require func_get_arg(0);
            };
            $include($this->template, $variables); // @phpstan-ignore arguments.count (read with func_get_arg())
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
