# Inertia PSR Adapter

A PSR-7 and PSR-15 compatible adapter for Inertia.js, enabling seamless server-side integration with any PHP framework that implements these standards.

## Features

- Full PSR-7 HTTP message interface support
- PSR-15 middleware for handling Inertia-specific headers and responses
- Support for partial reloads and asset versioning
- Compatible with any PSR-7 compliant framework
- Proper handling of JSON and HTML responses based on request type

## Installation

```bash
composer require solo/inertia-psr
```

## Usage

### Basic Setup

1. First, configure the Inertia class with your root template and asset information:

```php
use Solo\Inertia;

$inertia = new Inertia(
    rootTpl: '/path/to/root.php',
    assetsVersion: '1.0.0',
    js: '/dist/app.js',
    css: '/dist/app.css'
);
```

2. Add the InertiaMiddleware to your middleware stack:

```php
use Solo\Inertia\InertiaMiddleware;

// Using your container
$middleware = new InertiaMiddleware($container);
```

### Rendering Pages

To render an Inertia page:

```php
$response = $inertia->render(
    $request,
    $response,
    'Pages/Users/Index',
    [
        'users' => $users,
        'filters' => $filters
    ]
);
```

### Root Template Example

Create a root template (e.g., `root.php`):

```php
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <link href="<?= $css ?>" rel="stylesheet">
    <script src="<?= $js ?>" defer></script>
</head>
<body>
    <div id="app" data-page='<?= $page ?>'></div>
</body>
</html>
```

## Features in Detail

### Partial Reloads

The adapter automatically handles Inertia's partial reload feature through the `X-Inertia-Partial-Data` header, allowing for efficient partial page updates.

### Asset Versioning

Asset versioning is supported through the `assetsVersion` parameter, helping with cache busting and ensuring clients always have the latest assets.

### HTTP Method Override

The middleware automatically adjusts response status codes for specific HTTP methods:
- Converts 302 responses to 303 for PUT, PATCH, and DELETE requests
- Handles version mismatch redirects for GET requests

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the MIT license.
