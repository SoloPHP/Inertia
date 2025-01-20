# Inertia PSR Adapter

[![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://github.com/username/inertia-psr/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)

A PSR-7 and PSR-15 compatible adapter for Inertia.js, enabling seamless server-side integration with any PHP framework that implements these standards.

## Features

- Full PSR-7 HTTP message interface support
- PSR-15 middleware for handling Inertia-specific headers and responses
- Support for partial reloads and asset versioning
- Compatible with any PSR-7 compliant framework
- Proper handling of JSON and HTML responses based on request type
- Type-safe implementation with readonly properties
- Final classes for better predictability
- Shared props support across all pages

## Installation

```bash
composer require solophp/inertia
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

$middleware = new InertiaMiddleware($assetsVersion);
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

### Shared Properties

You can share data with all pages by setting the `sharedProps` request attribute:

```php
$request = $request->withAttribute('sharedProps', [
    'auth' => [
        'user' => $currentUser,
    ],
    'flash' => [
        'message' => $flashMessage,
    ],
]);
```

These props will be automatically merged with page-specific props during rendering.

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

### Shared Properties

The adapter supports sharing common data across all pages through the `sharedProps` request attribute. This is useful for data that should be available everywhere, such as user authentication status or flash messages.

### Type Safety

- All classes are marked as `final` to prevent inheritance issues
- Properties are marked as `readonly` for better immutability
- Strict typing is enforced throughout the codebase

### HTTP Method Override

The middleware automatically adjusts response status codes for specific HTTP methods:
- Converts 302 responses to 303 for PUT, PATCH, and DELETE requests
- Handles version mismatch redirects for GET requests

### Dependency Injection

The middleware now accepts the assets version directly, removing the container dependency for better flexibility and testing.

## Requirements

- PHP 8.1 or higher
- PSR-7 HTTP message implementation
- PSR-15 HTTP server-request handler implementation

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the MIT license.