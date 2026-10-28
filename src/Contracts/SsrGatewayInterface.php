<?php

declare(strict_types=1);

namespace Solo\Inertia\Contracts;

use Solo\Inertia\Ssr\RenderedPage;

/**
 * Renders a page on the server, so the first visit arrives as HTML the client
 * hydrates. Ssr\HttpGateway asks Inertia's Node SSR server.
 */
interface SsrGatewayInterface
{
    /**
     * @param array<string, mixed> $page The page object
     * @return RenderedPage|null The markup, or null to leave rendering to the client: a gateway that
     *                           fails reports why and returns null rather than throwing
     */
    public function render(array $page): ?RenderedPage;
}
