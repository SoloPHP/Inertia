# Validation

An Inertia form does not get a `422` with its errors. Like a classic form, it is redirected back to the page it came from, with the errors carried over to that page's `errors` prop. The client keeps what the user typed, shows the errors, and calls `onError` instead of `onSuccess`.

```php
return $this->inertia->back($request, $response, errors: [
    'title' => 'The title is required.',
    'email' => ['The email is invalid.', 'The email is taken.'],
]);
```

The next page Inertia renders has them:

```json
{"props": {"errors": {"title": "The title is required.", "email": ["The email is invalid.", "The email is taken."]}}}
```

A field maps to a message or to a list of messages; the client shows either. `errors` is always sent, as `{}` when there are none, even on partial reloads. The errors cross the redirect through the [flash store](/features/flash-data), and the page that shows them lets the store forget them.

```svelte
<script>
    import { useForm } from '@inertiajs/svelte'

    const form = useForm({ title: '' })
</script>

<form onsubmit={(e) => { e.preventDefault(); form.post('/tasks') }}>
    <input bind:value={form.title}>
    {#if form.errors.title}<p>{form.errors.title}</p>{/if}
    <button disabled={form.processing}>Save</button>
</form>
```

## From solophp/input

[solophp/input](https://solophp.github.io/Input/) throws one `ValidationException` with every error, keyed by field, each a list of failed rules. Turn it into a redirect once, in the middleware that handles exceptions:

```php
use Solo\Inertia\Inertia;
use Solo\Input\Exceptions\ValidationException;

final readonly class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private Inertia $inertia, private ResponseFactoryInterface $responses)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return $this->inertia->back($request, $this->responses->createResponse(), errors: array_map(
                // [['rule' => 'max', 'params' => ['255']]] -> "max:255"; translate rules into sentences here
                fn(array $failed) => implode(':', [$failed[0]['rule'], ...($failed[0]['params'] ?? [])]),
                $e->getErrors(),
            ));
        }
    }
}
```

A handler then validates with one line and the redirect happens by itself:

```php
$input = $this->inputs->fromBody(TaskStoreInput::class, $request);
```

List `InertiaMiddleware` before this middleware, so the redirect after a `PUT` or `PATCH` form is a `303`.

## Error bags

Two forms on one page with a field of the same name would show each other's errors. A form that names an error bag gets its errors under that name:

```js
router.post('/companies', data, { errorBag: 'createCompany' })
```

The client sends `X-Inertia-Error-Bag: createCompany` with the post and with the redirect it follows, and `render()` puts the errors under it:

```json
{"props": {"errors": {"createCompany": {"name": "The name is required."}}}}
```

The `useForm` helper and the `<Form>` component scope errors to their form without a bag.

## Precognition

Precognition validates a form while the user fills it in, with the server's own rules. The client sends the form's data with `Precognition: true` and the fields to check in `Precognition-Validate-Only`, and expects the rules to run without the handler: a `204` with `Precognition-Success: true` when those fields are valid, a `422` with their errors otherwise.

```svelte
<script>
    import { useForm } from '@inertiajs/svelte'

    // A method and a URL make the form precognitive
    const form = useForm('post', '/tasks', { title: '' })
</script>

<input bind:value={form.title} oninput={() => form.validate('title')}>
{#if form.errors.title}<p>{form.errors.title}</p>{/if}
<button onclick={() => form.submit()} disabled={form.processing}>Save</button>
```

`PrecognitionMiddleware` answers these requests, on the routes of such forms. It never runs the handler: a `RequestValidatorInterface` of yours finds the request's errors, the way the handler would, and the middleware keeps those of the fields the client asked about. Every other request goes on to the handler.

```php
use Psr\Http\Message\ServerRequestInterface;
use Solo\Application\RequestAttribute;
use Solo\Inertia\Contracts\RequestValidatorInterface;
use Solo\Input\Exceptions\ValidationException;
use Solo\Input\InputFactory;

/** Validates with the input class the route names: ->attribute('input', TaskStoreInput::class) */
final readonly class InputValidator implements RequestValidatorInterface
{
    public function __construct(private InputFactory $inputs)
    {
    }

    public function errors(ServerRequestInterface $request): array
    {
        $class = $request->getAttribute(RequestAttribute::ROUTE_ATTRIBUTES)['input']
            ?? throw new LogicException('The route names no input class to validate');

        try {
            $this->inputs->fromBody($class, $request, route: $request->getAttribute(RequestAttribute::PARAMS, []));
        } catch (ValidationException $e) {
            return Messages::of($e->getErrors()); // field => message
        }

        return [];
    }
}
```

```php
$r->group('/tasks', function (RouteCollector $r): void {
    $r->post('', [TaskController::class, 'store'])->attribute('input', TaskStoreInput::class);
    $r->patch('/{id:\d+}', [TaskController::class, 'update'])->attribute('input', TaskUpdateInput::class);
}, middlewares: [PrecognitionMiddleware::class]);
```

Since the handler never runs, a request that only validates can never create a task: a route that names no input class fails instead. The [Svelte skeleton](https://github.com/SoloPHP/Inertia-Svelte-Skeleton) has this validator in `src/Http/InputValidator.php`. Register your validator as `RequestValidatorInterface` in the container, so the middleware is autowired.
