<?php

namespace Vahalik\LaravelPhalcon\Routing;

use Illuminate\Http\Response;
use Illuminate\Routing\Route;

class ControllerDispatcher extends \Illuminate\Routing\ControllerDispatcher
{
    public function dispatch(Route $route, $controller, $method)
    {
        $modules = config('phalcon.modules', []);

        // If a module was defined, go find it.
        if (isset($route->action['phalconMetadata']['module'])) {
            try {
                $modClass = new $modules[$route->action['phalconMetadata']['module']]['className'];
                $modClass->registerServices(\Phalcon\Di\Di::getDefault());
            } catch (\Exception $e) {
                if (!config('phalcon.runtime.ignore_missing_modules', false)) {
                    throw $e;
                }
            }
        }

        if (method_exists($controller, 'beforeExecuteRoute')) {
            $controller->setDi(\Phalcon\Di\Di::getDefault());
            $controller->beforeExecuteRoute(\Phalcon\Di\Di::getDefault()->get('dispatcher'));
        }

        $result = parent::dispatch($route, $controller, $method);

        if (method_exists($controller, 'afterExecuteRoute')) {
            $controller->afterExecuteRoute(\Phalcon\Di\Di::getDefault()->get('dispatcher'));
        }

        if (!$result && \Phalcon\Di\Di::getDefault()->has('view')) {
            $view = \Phalcon\Di\Di::getDefault()->getShared('view');
            $view->start();
            $controller = request()->route()->getAction()['controller'];
            preg_match('/\\\\(\w+)Controller@(\w+)Action/', $controller, $matches);

            $view->render(strtolower($matches[1]), $matches[2]);
            $view->finish();

            return $view->getContent();
        }

        if ($result instanceof \Phalcon\Http\ResponseInterface) {
            return new Response($result->getContent(), $result->getStatusCode(), $result->getHeaders()->toArray());
        }

        return $result;
    }

    protected function resolveParameters(Route $route, $controller, $method)
    {
        if (empty($route->action['phalconMetadata'])) {
            return parent::resolveParameters($route, $controller, $method);
        }

        $base = $route->parametersWithoutNulls();

        return array_merge($base, array_slice($route->action['phalconMetadata'],  count($base)));
    }
}
