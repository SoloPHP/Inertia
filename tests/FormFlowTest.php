<?php

declare(strict_types=1);

namespace Solo\Inertia\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solo\Inertia\Inertia;
use Solo\Inertia\InertiaMiddleware;
use Solo\Inertia\Tests\Fixtures\CallbackHandler;

/**
 * A form that fails validation, then succeeds, through the middleware, as the
 * browser and the client make the requests: each response's cookies go with the
 * next request, and a redirect is followed with the same headers.
 */
final class FormFlowTest extends TestCase
{
    private Inertia $inertia;
    /** @var array<string, string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->inertia = self::inertia('v1');
    }

    private function app(ServerRequestInterface $request): ResponseInterface
    {
        $share = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle(Inertia::share($request, ['app.name' => 'Solo']));
            }
        };

        $router = new CallbackHandler(function (ServerRequestInterface $request): ResponseInterface {
            $response = self::response();

            return match ($request->getMethod() . ' ' . $request->getUri()->getPath()) {
                'GET /tasks/1/edit' => $this->inertia->render($request, $response, 'Tasks/Edit', [
                    'task' => ['id' => 1, 'title' => 'Old'],
                ]),
                'PUT /tasks/1' => $this->update($request, $response),
                'GET /tasks' => $this->inertia->render($request, $response, 'Tasks/Index', ['tasks' => []]),
                default => $response->withStatus(404),
            };
        });

        $response = (new InertiaMiddleware($this->inertia, new Psr17Factory()))
            ->process($request, new CallbackHandler(fn($request) => $share->process($request, $router)));

        foreach (self::setCookies($response) as $name => $line) {
            $value = self::cookieValue($line);
            if (str_contains($line, 'Max-Age=0')) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }

        return $response;
    }

    private function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $title = is_array($body) ? $body['title'] ?? '' : '';

        if (!is_string($title) || $title === '') {
            return $this->inertia->back($request, $response, errors: ['title' => 'The title is required.']);
        }

        return $this->inertia->redirect($request, $response, '/tasks', flash: ['success' => 'Task saved.']);
    }

    /** @param array<string, mixed> $data */
    private function send(string $method, string $path, array $data = []): ResponseInterface
    {
        $request = self::xhr("http://localhost$path", $method, [
            'Referer' => 'http://localhost/tasks/1/edit',
            'Content-Type' => 'application/json',
        ], $this->cookies, 'v1');
        $request->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $this->app($request);
    }

    /** The redirect the XHR follows: a GET to Location, with the same headers and the new cookies */
    private function follow(ResponseInterface $redirect): ResponseInterface
    {
        self::assertSame(303, $redirect->getStatusCode());

        return $this->app(self::xhr(
            'http://localhost' . $redirect->getHeaderLine('Location'),
            headers: ['Referer' => 'http://localhost/tasks/1/edit'],
            cookies: $this->cookies,
            version: 'v1',
        ));
    }

    public function testValidationErrorsThenSuccess(): void
    {
        $edit = self::page($this->follow($this->send('PUT', '/tasks/1', ['title' => ''])));

        self::assertSame('Tasks/Edit', $edit['component']);
        self::assertSame(['title' => 'The title is required.'], self::props($edit)['errors']);
        self::assertSame(['name' => 'Solo'], self::props($edit)['app']);
        self::assertSame([], $this->cookies, 'The errors are shown once');

        $again = self::page($this->app(self::xhr('http://localhost/tasks/1/edit', version: 'v1')));
        self::assertSame([], self::props($again)['errors']);

        $index = self::page($this->follow($this->send('PUT', '/tasks/1', ['title' => 'New'])));
        self::assertSame('Tasks/Index', $index['component']);
        self::assertSame(['success' => 'Task saved.'], $index['flash']);
        self::assertSame([], self::props($index)['errors']);
    }

    public function testFlashSurvivesTheReloadOfAStaleClient(): void
    {
        $redirect = $this->send('PUT', '/tasks/1', ['title' => 'New']);
        $this->inertia = self::inertia('v2');

        $conflict = $this->app(self::xhr('http://localhost/tasks', cookies: $this->cookies, version: 'v1'));
        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame('/tasks', $redirect->getHeaderLine('Location'));

        // The client reloads with a full page visit, which shows the flash data
        $page = self::htmlPage($this->app(self::visit('http://localhost/tasks', cookies: $this->cookies)));
        self::assertSame(['success' => 'Task saved.'], $page['flash']);
        self::assertSame('v2', $page['version']);
    }
}
