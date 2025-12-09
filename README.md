# Laravel to Phalcon bridge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nvahalik/laravel-phalcon.svg?style=flat-square)](https://packagist.org/packages/nvahalik/laravel-phalcon)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nvahalik/laravel-phalcon/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nvahalik/laravel-phalcon/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nvahalik/laravel-phalcon/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nvahalik/laravel-phalcon/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nvahalik/laravel-phalcon.svg?style=flat-square)](https://packagist.org/packages/nvahalik/laravel-phalcon)

This package allows you to use Laravel to ["Strangler Fig"](https://martinfowler.com/bliki/StranglerFigApplication.html) your Phalcon project.

The package works by:
1. Converting Phalcon routes to Laravel routes at boot time
2. Using a custom `ControllerDispatcher` to intercept requests to Phalcon routes
3. Bootstrapping the Phalcon DI container and application when those routes are hit
4. Executing Phalcon controllers and returning their response through Laravel

```
┌─────────────────────────────────────────────────────────────────┐
│                     Laravel Application                         │
├─────────────────────────────────────────────────────────────────┤
│  Request → Laravel Router → ControllerDispatcher                │
│                                    │                            │
│                    ┌───────────────┴───────────────┐            │
│                    ▼                               ▼            │
│           Laravel Controller            Phalcon Controller      │
│                                               │                 │
│                                    Phalcon DI + Application     │
│                                               │                 │
│                                    Phalcon Response → Laravel   │
└─────────────────────────────────────────────────────────────────┘
```

This also means that you get the "features" of introspection and Laravel's tooling.

## Installation

You can install the package via composer:

```bash
composer require nvahalik/laravel-phalcon
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-phalcon-config"
```

## Usage

1. Update the `phalcon.php` config file.
2. Move any customizations from your Phalcon's `index.php` file using the callback hooks:
3. Import your routes using `Route::fromPhalcon(...)`

If you run `./artisan route:list`, you should see your routes defined. 

## Configuration

### config/phalcon.php

```php
<?php

return [
    // Define your Phalcon modules
    'modules' => [
        'manager' => [
            'className' => 'YourApp\\Manager\\Module',
            'path' => '/path/to/apps/manager/Module.php',
        ],
        'admin' => [
            'className' => 'YourApp\\Admin\\Module',
            'path' => '/path/to/apps/admin/Module.php',
        ],
    ],

    // Path to Phalcon app's composer autoloader (if separate)
    'autoload_path' => '/path/to/phalcon-app/vendor/autoload.php',

    'runtime' => [
        // Skip routes with fully dynamic :controller/:action patterns
        'ignore_dynamic_controllers' => true,

        // Don't throw errors for missing controller classes
        'skip_missing_controllers' => true,
    ],
];
```

## Routing Setup

### routes/web.php

Import your existing Phalcon router into Laravel:

```php
<?php

use Illuminate\Support\Facades\Route;

// Import routes from your Phalcon router file
Route::fromPhalcon(require '/path/to/phalcon-app/config/routes.php');

// You can also scope routes by domain
Route::group(['domain' => 'admin.example.com'], function () {
    Route::fromPhalcon(require '/path/to/phalcon-app/config/admin-routes.php');
});
```

### Phalcon Router File Format

The package expects a standard Phalcon router:

```php
<?php
// /path/to/phalcon-app/config/routes.php

$router = new Phalcon\Mvc\Router(false);

$router->setDefaultModule('manager');
$router->setDefaultNamespace('YourApp\\Manager\\Controllers');

// Standard Phalcon route definitions
$router->addGet('/api/users', [
    'module' => 'manager',
    'namespace' => 'YourApp\\Manager\\Controllers',
    'controller' => 'Users',
    'action' => 'index',
]);

$router->addPost('/api/users', [
    'module' => 'manager',
    'namespace' => 'YourApp\\Manager\\Controllers',
    'controller' => 'Users',
    'action' => 'create',
]);

// Routes with parameters work too
$router->addGet('/api/users/{id:[0-9]+}', [
    'module' => 'manager',
    'namespace' => 'YourApp\\Manager\\Controllers',
    'controller' => 'Users',
    'action' => 'show',
]);

return $router;
```

## Lifecycle Hooks

The package provides hooks to initialize your Phalcon environment. Configure these in your `AppServiceProvider`:

### app/Providers/AppServiceProvider.php

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Vahalik\LaravelPhalcon\Facades\Phalcon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Called before middleware runs on Phalcon routes
        // Use this to set up defines, load helpers, initialize config
        Phalcon::beforeMiddlwareCallback(function () {
            define('APP_PATH', base_path('/phalcon-app/'));

            // Load Phalcon helpers and services
            require '/path/to/phalcon-app/helpers.php';
            require '/path/to/phalcon-app/services.php';

            // Initialize the Phalcon config service
            \Phalcon\Di\Di::getDefault()->get('config');
        });

        // Called after the DI is set up but before the route is dispatched
        // Use this to set up per-request services (account lookup, auth, etc.)
        Phalcon::beforeRouting(function () {
            $di = \Phalcon\Di\FactoryDefault::getDefault();

            // Example: Multi-tenant account lookup
            $subdomain = explode('.', request()->getHost())[0];

            $account = YourAccountLookup::find($subdomain);

            if (!$account) {
                $response = new \Phalcon\Http\Response();
                $response->setStatusCode(404);
                $response->setContent('Account not found');
                return $response->send();
            }

            // Register the account in Phalcon DI
            $di->set('account', fn() => $account, true);

            // Attach event listeners
            $eventsManager = $di->get('eventsManager');
            $eventsManager->attach('application:beforeHandleRequest',
                function ($event, $application) use ($di) {
                    // Set up per-request services here
                }
            );
        });
    }
}
```

## Phalcon Module Structure

Your Phalcon modules must implement `ModuleDefinitionInterface`:

```php
<?php

namespace YourApp\Manager;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\ModuleDefinitionInterface;

class Module implements ModuleDefinitionInterface
{
    public const CONTROLLER_NAMESPACE = 'YourApp\\Manager\\Controllers';

    public function registerAutoloaders(?DiInterface $di = null)
    {
        // Usually handled by Composer in a Laravel context
    }

    public function registerServices(DiInterface $di)
    {
        // Register module-specific services
        $di->set('session', function () use ($di) {
            // Session configuration
        }, true);

        // Set up dispatcher events
        $dispatcher = $di->get('dispatcher');
        $eventsManager = new \Phalcon\Events\Manager();

        $eventsManager->attach('dispatch:beforeException',
            function ($event, $dispatcher, $exception) {
                // Handle exceptions
            }
        );

        $dispatcher->setEventsManager($eventsManager);
    }
}
```

## Autoloading

Add your Phalcon namespaces to Laravel's `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "YourApp\\": "phalcon-app/lib/",
            "YourApp\\Manager\\": "phalcon-app/apps/manager/",
            "YourApp\\Manager\\Controllers\\": "phalcon-app/apps/manager/controllers/"
        }
    }
}
```

Run `composer dump-autoload` after updating.

## How Route Conversion Works

The package converts Phalcon routes to Laravel routes:

| Phalcon Pattern | Laravel Pattern |
|-----------------|-----------------|
| `/users/{id:[0-9]+}` | `/users/{id}` with `->where('id', '[0-9]+')` |
| `/users/([0-9]+)` | `/users/{param}` with regex constraint |
| `/(admin\|manager)/dashboard` | Creates 2 routes: `/admin/dashboard` and `/manager/dashboard` |

## What Gets Disabled

For Phalcon routes, these Laravel middlewares are automatically disabled:
- CSRF verification
- Cookie encryption
- Session handling
- Laravel route model binding

This ensures Phalcon handles these concerns itself.

## Key Components

| Component | Purpose |
|-----------|---------|
| `LaravelPhalconServiceProvider` | Registers the `Route::fromPhalcon()` macro and custom dispatcher |
| `ControllerDispatcher` | Intercepts Phalcon routes and bootstraps the Phalcon application |
| `PhalconCompatability` middleware | Runs the pre-middleware callback |
| `Application` | Modified Phalcon MVC application that accepts route info from Laravel |

## Tips

1. **Gradual Migration**: You can mix Laravel and Phalcon routes. Native Laravel routes work normally.
2. **Shared Services**: Access Phalcon's DI from Laravel:
   ```php
   $phalconDi = app(\Phalcon\Di\DiInterface::class);
   ```
3. **Testing**: Laravel's testing tools work, but Phalcon controllers won't use Laravel's request/response objects internally.
4. **Performance**: Routes are converted at boot time. Use route caching (`php artisan route:cache`) in production.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Nick Vahalik](https://github.com/nvahalik)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
