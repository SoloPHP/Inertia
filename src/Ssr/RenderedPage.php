<?php

declare(strict_types=1);

namespace Solo\Inertia\Ssr;

/**
 * A page rendered on the server: what goes into the root template instead of the
 * page object's script element and the empty root element.
 */
final readonly class RenderedPage
{
    /**
     * @param string $head Elements for <head>, such as the title and meta tags
     * @param string $body The page object's script element and the rendered root element
     */
    public function __construct(
        public string $head,
        public string $body,
    ) {
    }
}
