---
layout: home

hero:
  name: Solo Inertia
  text: Inertia.js 3 for PSR-7 and PSR-15
  tagline: Server-driven single-page apps with any PSR framework — pages, partial reloads, deferred, merge and once props, flash data, history encryption and server-side rendering.
  image:
    src: /logo.svg
    alt: Solo Inertia
  actions:
    - theme: brand
      text: Get Started
      link: /guide/installation
    - theme: alt
      text: Upgrading from 1.x
      link: /guide/upgrading
    - theme: alt
      text: View on GitHub
      link: https://github.com/SoloPHP/Inertia

features:
  - icon: 📄
    title: The whole protocol
    details: 'Inertia 3: the page object in a script element, partial reloads by path with only, except and reset, version conflicts, external and fragment redirects, error bags, flash data, history flags.'
  - icon: 🧩
    title: Props with rules
    details: 'Inertia::always(), optional(), defer(), merge(), deepMerge(), once() and scroll() — the categories the protocol defines, combinable and resolvable at any depth.'
  - icon: 💤
    title: Lazy by default
    details: 'A Closure prop is called only when the response sends it, so a partial reload costs only the props it asks for.'
  - icon: 🍪
    title: Flash without sessions
    details: 'Errors and flash data reach the next page in a signed cookie: no session, no server-side storage, nothing to reset in a worker. Or bring your own store.'
  - icon: 🔌
    title: PSR all the way
    details: 'Any PSR-7 messages, PSR-15 middleware, PSR-17 factories and PSR-3 logger; server-side rendering over any PSR-18 client.'
  - icon: ⚡
    title: Live validation
    details: 'Precognition checks a form against the server rules while the user types: PrecognitionMiddleware validates without ever running the handler.'
  - icon: 🧵
    title: Worker-safe
    details: 'The service holds configuration only. Shared props and flash data travel with the request, so one instance serves FrankenPHP and RoadRunner workers too.'
---

<style>
:root {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: linear-gradient(135deg, #8b5cf6 0%, #06b6d4 100%);
  --vp-home-hero-image-background-image: linear-gradient(135deg, #8b5cf630 0%, #06b6d430 100%);
  --vp-home-hero-image-filter: blur(44px);
}

.VPHero .VPImage {
  max-width: 200px;
  max-height: 200px;
}
</style>

## Quick Example

```php
use Solo\Inertia\Inertia;

final readonly class TaskController
{
    public function __construct(private Inertia $inertia, private TaskRepository $tasks)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->inertia->render($request, $response, 'Tasks/Index', [
            'tasks' => fn() => $this->tasks->all(),                 // called only when sent
            'stats' => Inertia::defer(fn() => $this->tasks->stats()), // loaded right after the page
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $title = trim((string) ($request->getParsedBody()['title'] ?? ''));
        if ($title === '') {
            return $this->inertia->back($request, $response, errors: ['title' => 'The title is required.']);
        }

        $this->tasks->create($title);

        return $this->inertia->redirect($request, $response, '/tasks', flash: ['success' => 'Task created.']);
    }
}
```

## Installation

```bash
composer require solophp/inertia
```

**Requirements:** PHP 8.3+, a PSR-7 and PSR-17 implementation, and an Inertia.js 3 client — `@inertiajs/svelte`, `@inertiajs/vue3` or `@inertiajs/react`. The [Svelte skeleton](https://github.com/SoloPHP/Inertia-Svelte-Skeleton) is a complete application to start from.
