<?php

namespace Vahalik\LaravelPhalcon\Routing;

use Illuminate\Http\Response;
use Illuminate\Routing\Route;

class ControllerDispatcher extends \Illuminate\Routing\ControllerDispatcher
{
    public function dispatch(Route $route, $controller, $method)
    {
        if (! $route->action['phalconMetadata']) {
            return parent::dispatch($route, $controller, $method);
        }

        require '/code/vendor/autoload.php';

        $modules = config('phalcon.modules', []);

        $phalcon = app(\Vahalik\LaravelPhalcon\Phalcon\Application::class);

        $phalcon->registerModules($modules);
        $di = \Phalcon\Di\FactoryDefault::getDefault();
        $phalcon->setDI($di);

        $this->setUpPhalconEnvironment();

        $phalcon->setEventsManager($di->getShared('eventsManager'));

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

        // Up to this...
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

//    protected function resolveParameters(Route $route, $controller, $method)
//    {
//        if (empty($route->action['phalconMetadata'])) {
//            return parent::resolveParameters($route, $controller, $method);
//        }
//
//        $base = $route->parametersWithoutNulls();
//
//        return array_merge($base, array_slice($route->action['phalconMetadata'], count($base)));
//    }

    public function setUpPhalconEnvironment()
    {
        $di = \Phalcon\Di\FactoryDefault::getDefault();

        $host = request()->getHost();
        $subdomain = explode('.', $host)[0];

        if ($subdomain === 'admin') {
            return;
        }

        // Lookup Account from Subdomain
        $accountLookupQuery = new \Rentvine\Core\Account\Queries\AccountFromSubdomain([
            'subdomain' => $subdomain,
        ]);

        if (!$accountLookupQuery->execute()) {
            $response = new \Phalcon\Http\Response();

            $response->setStatusCode(404);
            $response->setContent('No Account Found');

            return $response->send();
        }

        // Get Account Data
        $accountData = $accountLookupQuery->results;

        if (!$accountData) {
            $response = new \Phalcon\Http\Response();

            $response->setStatusCode(404);
            $response->setContent('No Account Found');

            return $response->send();
        }

        // Check if Subdomain is Primary (redirect if not)
        $isPrimarySubdomain = \Rentvine\ArrayHelper::getValue($accountData, 'isPrimarySubdomain', 1);

        if (!$isPrimarySubdomain) {
            $response = new \Phalcon\Http\Response();

            $response->setStatusCode(202);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode([
                'message' => 'Subdomain is not primary',
                'redirectTo' => \Rentvine\ArrayHelper::getValue($accountData, 'subdomain'),
            ]));

            return $response->send();
        }

        // Set Account Data
        $account = new \Rentvine\Core\Account\Models\Accounts($accountData);

        foreach (\Rentvine\Core\Account\Models\Accounts::JSON_FIELDS as $field) {
            $value = $account->{$field};

            if (!empty($value) && is_string($value)) {
                $account->assign([
                    $field => json_decode($value, true),
                ]);
            }
        }

        $di->set('account', function () use ($account) {
            return $account;
        }, true);

        $eventsManager = \Phalcon\Di\FactoryDefault::getDefault()->get('eventsManager');
        $eventsManager->attach('application:afterStartModule', function ($event, $application, $module) {
        });

        $eventsManager->attach('application:beforeHandleRequest', function ($event, $application) use ($di) {
            $account = $di->get('account');
            $config = $di->getShared('config');

            $filesystem = $di->get('filesystem');
            $factory = new \Rentvine\Filesystem\Factory();

            // public account file system
            $options = $config->filesystems->accountPublic->toArray();
            $adapterName = \Rentvine\ArrayHelper::getValue($options, 'adapter');
            unset($options['adapter']);
            $publicAdapter = $factory->newInstance($adapterName, array_merge($options, [
                'path' => $account->directory . DIRECTORY_SEPARATOR,
            ]));
            $publicFilesystem = new \League\Flysystem\Filesystem($publicAdapter);
            $filesystem->dangerouslyMountFilesystems('public', $publicFilesystem);

            // private account file system
            $options = $config->filesystems->accountPrivate->toArray();
            $adapterName = \Rentvine\ArrayHelper::getValue($options, 'adapter');
            unset($options['adapter']);
            $privateAdapter = $factory->newInstance($adapterName, array_merge($options, [
                'path' => $account->directory . DIRECTORY_SEPARATOR,
            ]));
            $privateFilesystem = new \League\Flysystem\Filesystem($privateAdapter);
            $filesystem->dangerouslyMountFilesystems('private', $privateFilesystem);

            // exchange file system
            $options = $config->filesystems->accountPrivate->toArray();
            $adapterName = \Rentvine\ArrayHelper::getValue($options, 'adapter');
            unset($options['adapter']);
            $exchangeAdapter = $factory->newInstance($adapterName, array_merge($options, [
                'path' => $account->directory . DIRECTORY_SEPARATOR . 'exchange' . DIRECTORY_SEPARATOR,
            ]));
            $exchangeFilesystem = new \League\Flysystem\Filesystem($exchangeAdapter);
            $filesystem->dangerouslyMountFilesystems('exchange', $exchangeFilesystem);
        });
    }
}
