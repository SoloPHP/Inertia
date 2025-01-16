<?php declare(strict_types=1);

namespace Solo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Closure;

/**
 * Inertia adapter for server-side rendering.
 *
 * This class handles the server-side portion of Inertia.js integration,
 * managing both initial page loads and subsequent Inertia requests.
 * It supports partial reloads, asset versioning, and dynamic props.
 */
final class Inertia
{
    /**
     * Initialize a new Inertia instance.
     *
     * @param string $rootTpl Path to the root template file
     * @param string $assetsVersion Version identifier for assets cache busting
     * @param string $js Path to the JavaScript bundle
     * @param string $css Path to the CSS stylesheet
     */
    public function __construct(
        private readonly string $rootTpl,
        private readonly string $assetsVersion,
        private readonly string $js,
        private readonly string $css
    )
    {
    }

    /**
     * Render an Inertia response.
     *
     * Handles both initial page loads and Inertia requests, returning either
     * a full HTML page or a JSON response as appropriate.
     *
     * @param ServerRequestInterface $request The incoming HTTP request
     * @param ResponseInterface $response The response object to modify
     * @param string $component The name of the frontend component to render
     * @param array $props The props to pass to the component
     * @return ResponseInterface             The modified response
     */
    public function render(ServerRequestInterface $request, ResponseInterface $response, string $component, array $props = []): ResponseInterface
    {
        $props += $request->getAttribute('inertiaCommonProps') ?? [];

        $page = $this->getPage($request, $component, $props);
        if ($request->hasHeader('X-Inertia')) {
            $response->getBody()->write(json_encode($page, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
            return $response->withHeader('Content-Type', 'application/json');
        }
        $data = $this->fetchRootTpl(['app' => $this->getRootTplVars($page)]);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Get variables for the root template.
     *
     * @param array $page The page data
     * @return array      Template variables
     */
    private function getRootTplVars(array $page): array
    {
        return [
            'page' => json_encode($page, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT),
            'css' => $this->css,
            'js' => $this->js,
            'props' => $page['props']
        ];
    }

    /**
     * Build the page data array.
     *
     * Handles partial reloads and resolves any Closure props.
     *
     * @param ServerRequestInterface $request The incoming HTTP request
     * @param string $component The component name
     * @param array $props The component props
     * @return array                       The complete page data
     */
    private function getPage(ServerRequestInterface $request, string $component, array $props = []): array
    {
        if ($request->hasHeader('X-Inertia-Partial-Data')) {
            $only = explode(',', $request->getHeaderLine('X-Inertia-Partial-Data'));
            $props = ($only && $request->getHeaderLine('X-Inertia-Partial-Component') === $component)
                ? array_intersect_key($props, array_flip($only))
                : $props;
        }

        array_walk_recursive($props, function (&$prop) {
            if ($prop instanceof Closure) {
                $prop = $prop();
            }
        });

        return [
            'component' => $component,
            'props' => $props,
            'url' => $request->getRequestTarget(),
            'version' => $this->assetsVersion
        ];
    }

    /**
     * Fetch and render the root template.
     *
     * @param array $props Variables to extract into template scope
     * @return string     The rendered template content
     */
    private function fetchRootTpl(array $props = []): string
    {
        extract($props);

        ob_start();
        include($this->rootTpl);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
}