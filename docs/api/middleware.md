# Middleware

## InertiaMiddleware

`Solo\Inertia\InertiaMiddleware` — the protocol rules that apply to a whole response, whoever made it.

```php
public function __construct(Inertia $inertia, ResponseFactoryInterface $responses)
```

For a request without `X-Inertia`, it only adds `Vary: X-Inertia` to the response. For an Inertia request:

| When | Then |
|---|---|
| A GET whose `X-Inertia-Version` is not `$inertia->version` | `409` with `X-Inertia-Location` (the request URL) and `X-Inertia-Version`, before any handler runs |
| A `302` after a `PUT`, `PATCH` or `DELETE` | The status becomes `303` |
| A redirect whose `Location` has a `#fragment`, unless prefetched | A `409` with `X-Inertia-Redirect` instead of `Location`; other headers stay |
| Always | `Vary: X-Inertia`, added once |

It never adds `X-Inertia: true`: only `render()` marks a response as a page. List it before the middleware that turns exceptions into responses.

## PrecognitionMiddleware

`Solo\Inertia\PrecognitionMiddleware` — answers the requests the client makes to validate a form while the user fills it in. See [Precognition](/features/validation#precognition).

```php
public function __construct(RequestValidatorInterface $validator, ResponseFactoryInterface $responses)
```

| Request | Response |
|---|---|
| `Precognition: true`, and the fields asked about in `Precognition-Validate-Only` are valid | `204` with `Precognition: true` and `Precognition-Success: true`; the handler does not run |
| `Precognition: true`, and some of those fields are invalid | `422` with `Precognition: true` and `{"errors": {...}}`, those fields' errors only; the handler does not run |
| Any other | The handler's response |

Every response varies by `Precognition`. Without `Precognition-Validate-Only`, every field counts; a field asked about brings its nested errors too, so `address` covers `address.city`.

### RequestValidatorInterface

`Solo\Inertia\Contracts\RequestValidatorInterface` — validates a request the way its handler would, without running it.

```php
/** @return array<string, mixed> field => message or messages; [] for a valid request */
public function errors(ServerRequestInterface $request): array;
```

The middleware runs after routing, so the request carries its route's attributes and parameters.

## EncryptHistoryMiddleware

`Solo\Inertia\EncryptHistoryMiddleware` — sets the `Inertia::ENCRYPT_HISTORY` request attribute, so the pages rendered below it encrypt their history state, unless `render()` says `encryptHistory: false`. It has no dependencies. See [History Encryption](/features/history-encryption).
