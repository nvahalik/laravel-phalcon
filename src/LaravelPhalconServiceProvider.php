<?php

namespace Vahalik\LaravelPhalcon;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Phalcon\Mvc\Router\Route as PhalconRoute;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vahalik\LaravelPhalcon\Http\Middleware\PhalconCompatability;
use Vahalik\LaravelPhalcon\Routing\ControllerDispatcher;

class LaravelPhalconServiceProvider extends PackageServiceProvider
{
    public function registeringPackage()
    {
        $this->app->alias(ControllerDispatcher::class, \Illuminate\Routing\Contracts\ControllerDispatcher::class);

        IlluminateRoute::macro('addMethodParams', function (array $params) {
            $this->action['methodParams'] = array_merge($this->action['methodParams'] ?? [], $params);

            return $this;
        });

        IlluminateRoute::macro('addMethodParam', function (string $key, mixed $default = null) {
                $this->action['methodParams'][$key] ?? $default;

            return $this;
        });

        IlluminateRoute::macro('getMethodParams', function () {
            return $this->action['methodParams'];
        });

        Route::macro('fromPhalcon', function (\Phalcon\Mvc\Router $router) {
            /* @var PhalconRoute $route */
            $routeNamespace = $router->getDefaults()['namespace'];

            foreach ($router->getRoutes() as $route) {
                $paths = $route->getPaths();

                $extraParams = collect($paths)
                    ->except(['controller', 'action'])
                    ->toArray();

                $fullClass = $routeNamespace . ucfirst($paths['controller']) . 'Controller';
                $actionName = $paths['action'] . 'Action';

                $wheres = [];
                $paramPosition = 0;

                $split = collect(explode('/', $route->getPattern()))
                    ->map(function ($item, $idx) use ($paths, &$wheres, &$paramPosition) {
                        // Phalcon uses a 1-indexed numbering system for parameters. When we encounter a parameter, we
                        // need to adjust the position accordingly.
                        if (in_array(substr($item, 0, 1), ['{', '(', ':'])) {
                            $paramPosition++;
                        }

                        if (substr($item, 0, 1) === '(') {
                            // The item name comes from the parameters in the "paths" array.
                            $itemName = array_search($paramPosition, $paths);
                            $wheres[$itemName] = substr($item, 1, -1);
                            return '{' . $itemName . '}';
                        } else if (substr($item, 0, 1) === '{') {
                            // Pull the item name out of the parameter definition.
                            $item = str_replace(['{', '}'], '', $item);
                            list($itemName, $regex) = explode(':', $item);
                            if ($regex) {
                                $wheres[$itemName] = $regex;
                            }
                            return '{' . $itemName . '}';
                        } else {
                            // We don't support any of the fancy stuff (:controller, :action, etc.) in our routes.
                            return $item;
                        }
                    });

                $pattern = $split->implode('/');

                Route::addRoute($route->getHttpMethods(), $pattern, [$fullClass, $actionName])
                    ->middleware(PhalconCompatability::class)
                    ->where($wheres)
                    ->withoutMiddleware([
                        VerifyCsrfToken::class, ValidateCsrfToken::class,
                        \Illuminate\Cookie\Middleware\EncryptCookies::class,
                        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                        \Illuminate\Session\Middleware\StartSession::class,
                        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                        \Illuminate\Routing\Middleware\SubstituteBindings::class,
                    ])
                    ->addMethodParams($extraParams);
            }
        });
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-phalcon');
    }
}
