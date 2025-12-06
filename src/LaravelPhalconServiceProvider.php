<?php

namespace Vahalik\LaravelPhalcon;

use _PHPStan_e870ac104\Nette\Neon\Exception;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Phalcon\Mvc\Router\Route as PhalconRoute;
use ReflectionClass;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vahalik\LaravelPhalcon\Http\Middleware\PhalconCompatability;
use Vahalik\LaravelPhalcon\Routing\ControllerDispatcher;

class LaravelPhalconServiceProvider extends PackageServiceProvider
{
    public function registeringPackage()
    {
        $this->app->alias(ControllerDispatcher::class, \Illuminate\Routing\Contracts\ControllerDispatcher::class);

        IlluminateRoute::macro('addPhalconMetadata', function (array $params) {
            $this->action['phalconMetadata'] = array_merge($this->action['phalconMetadata'] ?? [], $params);

            return $this;
        });

        IlluminateRoute::macro('getPhalconMetadata', function () {
            return $this->action['phalconMetadata'];
        });

        Route::macro('fromPhalcon', function (\Phalcon\Mvc\Router $router) {
            $getRouteParams = function (string $pattern) {
                return collect(explode('/', $pattern))
                    ->map(fn ($i) => in_array(substr($i, 0, 1), ['{', '(', ':']) ? $i : null)->filter()->values();
            };

            $parseRouteRegex = function (string $pattern, bool $validate = false): ?array {
                if (preg_match('/^\(([a-z0-9_|-]+)\)$/i', $pattern, $matches)) {
                    $options = explode('|', $matches[1]);

                    if ($validate) {
                        foreach ($options as $option) {
                            if ($option === '' || !preg_match('/^[a-z0-9_-]+$/i', $option)) {
                                return null;
                            }
                        }
                    }

                    return $options;
                }

                return null;
            };

            $routeNamespace = $router->getDefaults()['namespace'];

            /* @var PhalconRoute $route */
            foreach ($router->getRoutes() as $route) {
                $paths = $route->getPaths();

                $extraParams = collect($paths)->toArray();

                // Class to path.
                $classes = [($paths['namespace'] ?? $routeNamespace) . '\\' . ucfirst($paths['controller']) . 'Controller' => $route->getPattern()];
                $actionName = $paths['action'] . 'Action';

                if (is_numeric($paths['controller'])) {
                    $params = $getRouteParams($route->getPattern(), $paths);
                    if ($options = $parseRouteRegex($params[$paths['controller'] - 1])) {
                        $realPaths = array_fill(0, count($options), $route->getPattern());
                        // We have options. We really want these each to be their own route!
                        $realControllerNames = array_map(fn ($i) => Str::studly($i), $options);
                        $realPaths = array_map(fn ($i, $idx) => str_replace($params[$paths['controller'] - 1], $options[$idx], $i), $realPaths, array_keys($realPaths));
                        $classes = array_map(fn ($i) => ($paths['namespace'] ?? $routeNamespace) . '\\' . $i . 'Controller', $realControllerNames);
                        $classes = array_combine($classes, $realPaths);
                    } else {
                        if (! config('phalcon.runtime.ignore_dynamic_controllers', true)) {
                            throw new Exception('Unable to parse dynamic controller route: ' . $route->getPattern());
                        }
                    }
                }

                foreach ($classes as $fullClass => $realPath) {
                    // Make sure that the class is available. If not, skip this route or throw an exception based on the
                    // configuration.
                    try {
                        (new ReflectionClass($fullClass))->getFileName();
                    } catch (\ReflectionException $e) {
                        if (config('phalcon.runtime.skip_missing_controllers', false)) {
                            continue;
                        }
                        throw $e;
                    }

                    $wheres = [];
                    $paramPosition = 0;

                    // Convert the routes to "native" Laravel routes.
                    $split = collect(explode('/', $realPath))
                        ->map(function ($item) use ($paths, &$wheres, &$paramPosition) {
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
                            VerifyCsrfToken::class,
                            ValidateCsrfToken::class,
                            \Illuminate\Cookie\Middleware\EncryptCookies::class,
                            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                            \Illuminate\Session\Middleware\StartSession::class,
                            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                            \Illuminate\Routing\Middleware\SubstituteBindings::class,
                        ])
                        ->addPhalconMetadata($extraParams);
                }
            }

            return $this;
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
            ->name('laravel-phalcon')
            ->hasConfigFile('phalcon');
    }
}
