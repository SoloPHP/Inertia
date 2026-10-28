# Redirects

After a form, a handler redirects to a page, and the client follows the redirect with a GET:

```php
return $this->inertia->redirect($request, $response, '/users');
```

`redirect()` sets a `302` and the `Location` header; `status` takes another 3xx. Its [flash](/features/flash-data), `errors`, `clearHistory` and `preserveFragment` options reach the next page the redirect leads to.

## 303 after PUT, PATCH and DELETE

A browser follows a `302` with the method of the request that got it, so a redirect after a `PUT` would `PUT` again. `InertiaMiddleware` turns a `302` after a `PUT`, `PATCH` or `DELETE` into a `303 See Other`, which the browser follows with a `GET` — whoever made the redirect, your error middleware included, as long as `InertiaMiddleware` is listed before it.

## Back

`back()` redirects to the page the request came from, by its `Referer`, which is how a form with errors returns to itself:

```php
return $this->inertia->back($request, $response, errors: ['email' => 'This email is taken.']);
```

Only the path and query string of the `Referer` are used, and only when it comes from the request's own host; otherwise the redirect goes to the fallback, `/` unless given:

```php
return $this->inertia->back($request, $response, fallback: '/tasks');
```

## External redirects

A redirect to another site, or to a page of your own that is not part of the Inertia app, cannot be followed by an XHR. `location()` makes a full page load instead:

```php
return $this->inertia->location($request, $response, 'https://github.com/login/oauth/authorize?...');
```

On an Inertia visit, it answers `409 Conflict` with `X-Inertia-Location`, and the client sets `window.location`. A browser request gets a plain `302`.

## Fragments

An XHR follows a redirect and loses its fragment, so `InertiaMiddleware` turns a redirect to a URL with a `#fragment` into a `409 Conflict` with `X-Inertia-Redirect`: the client visits that URL itself, fragment included. Cookies and other headers of the redirect stay. Prefetch requests are left alone.

```php
return $this->inertia->redirect($request, $response, '/article/new-slug#comments');
```

The fragment of the visit that led to a redirect — `/article/old-slug#section` — is not sent to the server at all. With `preserveFragment`, the client carries it over to the redirect's target:

```php
return $this->inertia->redirect($request, $response, '/article/new-slug', preserveFragment: true);
```
