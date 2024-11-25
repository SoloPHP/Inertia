<?php declare(strict_types=1);

namespace Solo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Inertia
{
    private string $rootTpl;
    private string $assetsVersion;
    private string $js;
    private string $css;


    public function __construct(string $rootTpl, string $assetsVersion, string $js, string $css)
    {
        $this->rootTpl = $rootTpl;
        $this->assetsVersion = $assetsVersion;
        $this->js = $js;
        $this->css = $css;
    }

    public function render(ServerRequestInterface $request, ResponseInterface $response, string $component, array $props = []): ResponseInterface
    {
        $props += $request->getAttribute('inertiaCommonProps') ?? [];

        $page = $this->getPage($request, $component, $props);
        if ($request->hasHeader('X-Inertia')) {
            $response->getBody()->write(json_encode($page));
            return $response->withHeader('Content-Type', 'application/json');
        }
        $data = $this->fetchRootTpl(['app' => $this->getRootTplVars($page)]);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function getRootTplVars(array $page): array
    {
        return [
            'page' => json_encode($page),
            'css' => $this->css,
            'js' => $this->js,
            'props' => $page['props']
        ];
    }

    private function getPage(ServerRequestInterface $request, string $component, array $props = []): array
    {
        if ($request->hasHeader('X-Inertia-Partial-Data')) {
            $only = explode(',', $request->getHeaderLine('X-Inertia-Partial-Data'));
            $props = ($only && $request->getHeaderLine('X-Inertia-Partial-Component') === $component)
                ? array_intersect_key($props, array_flip($only))
                : $props;
        }

        array_walk_recursive($props, function (&$prop) {
            if ($prop instanceof \Closure) {
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