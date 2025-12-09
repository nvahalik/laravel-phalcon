<?php

namespace Vahalik\LaravelPhalcon\Routing;

use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Vahalik\LaravelPhalcon\Facades\Phalcon;

class ControllerDispatcher extends \Illuminate\Routing\ControllerDispatcher
{
    public function dispatch(Route $route, $controller, $method)
    {
        // If this isn't a Phalcon route, do the normal Laravel thing.
        if (! $route->action['phalconMetadata']) {
            return parent::dispatch($route, $controller, $method);
        }

        // We need the "original application's" autoloader.
        if (!empty($phalconAutoloadPath = config('phalcon.autoload_path'))) {
            require $phalconAutoloadPath;
        }

        $di = \Phalcon\Di\FactoryDefault::getDefault();

        // Create our application and apply the modules.
        $phalcon = app(\Vahalik\LaravelPhalcon\Phalcon\Application::class);
        $phalcon->registerModules(config('phalcon.modules', []));
        $phalcon->setDI($di);

        // Allow the application to do any setup before handling the routes.s
        Phalcon::runBeforeRouting($di, $phalcon);

        // Set the eventsManager if it is defined.
        if ($eventsManager = $di->getShared('eventsManager')) {
            $phalcon->setEventsManager($eventsManager);
        }

        $phalcon->prepareDispatcherWithRoute(function ($dispatcher) use ($route) {
            $metadata = $route->action['phalconMetadata'];
            $dispatcher->setModuleName($metadata['module']);
            $dispatcher->setNamespaceName($metadata['namespace']);
            $dispatcher->setControllerName($metadata['controller']);
            $dispatcher->setActionName($metadata['action']);
            // If there are "hard coded parameters" we merge those in.

            $params = $route->parameters();
            $onlyParams = collect($route->action['phalconMetadata'])->except('controller', 'action', 'module', 'namespace')->toArray();
            if (count($onlyParams) > count($params)) {
                $params = array_merge($params, array_slice($onlyParams, count($params)));
            }

            $dispatcher->setParams($params);
        });

        $phalcon->setModuleName($route->action['phalconMetadata']['module']);

        // Have Phalcon handle the route.
        $result = $phalcon->handle($route->uri());

        if ($result instanceof \Phalcon\Http\ResponseInterface) {
            if (!$result->hasHeader('Status')) {
                $statusCode = 200;
            } else {
                $statusCode = (int)$result->getHeaders()->get('Status');
            }
            if ($statusCode == '0') {
            }
            return new Response($result->getContent(), $statusCode, $result->getHeaders()->toArray());
        }

        return $result;
    }
}
