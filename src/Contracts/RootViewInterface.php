<?php

declare(strict_types=1);

namespace Solo\Inertia\Contracts;

/**
 * The HTML document of a first visit: the page the browser loads before the client
 * takes over. PhpRootView renders a PHP template; an implementation may use any
 * template engine, as long as it prints $head and $body as they are.
 */
interface RootViewInterface
{
    /**
     * @param array<string, mixed> $page     The page object, as the client gets it
     * @param string               $head     Elements for <head> from server-side rendering, or ''
     * @param string               $body     Markup for <body>: the page object's script element and the
     *                                       root element, or what server-side rendering returned
     * @param array<string, mixed> $viewData Data for the template only, never sent to the client
     */
    public function render(array $page, string $head, string $body, array $viewData): string;
}
