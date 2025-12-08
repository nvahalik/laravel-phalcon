<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Vahalik\LaravelPhalcon\Phalcon;

use Closure;
use Phalcon\Application\AbstractApplication;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application\Exception;

/**
 * Phalcon\Mvc\Application
 *
 * This component encapsulates all the complex operations behind instantiating
 * every component needed and integrating it with the rest to allow the MVC
 * pattern to operate as desired.
 *
 *```php
 * use Phalcon\Mvc\Application;
 *
 * class MyApp extends Application
 * {
 *     /**
 *      * Register the services here to make them general or register
 *      * in the ModuleDefinition to make them module-specific
 *      *\/
 *     protected function registerServices()
 *     {
 *
 *     }
 *
 *     /**
 *      * This method registers all the modules in the application
 *      *\/
 *     public function main()
 *     {
 *         $this->registerModules(
 *             [
 *                 "frontend" => [
 *                     "className" => "Multiple\\Frontend\\Module",
 *                     "path"      => "../apps/frontend/Module.php",
 *                 ],
 *                 "backend" => [
 *                     "className" => "Multiple\\Backend\\Module",
 *                     "path"      => "../apps/backend/Module.php",
 *                 ],
 *             ]
 *         );
 *     }
 * }
 *
 * $application = new MyApp();
 *
 * $application->main();
 *```
 */
class Application extends AbstractApplication
{
    /**
     * @var bool
     */
    protected $implicitView = true;

    /**
     * @var bool
     */
    protected $sendCookies = true;

    /**
     * @var bool
     */
    protected $sendHeaders = true;
    /**
     * @var Closure|mixed|null
     */
    private ?Closure $dispatcherPrepareCallback = null;

    private string $routedModuleName;

    /**
     * Handles a MVC request
     *
     * @param string $uri
     * @return ResponseInterface|bool
     */
    public function handle(string $uri)
    {
        $container = $this->container;

        if ($container === null) {
            throw new Exception(
                "A dependency injection container is required to access internal services"
            );
        }

        $eventsManager = $this->eventsManager;

        /**
         * Call boot event, this allow the developer to perform initialization
         * actions
         */
        if ($eventsManager !== null && $eventsManager->fire("application:boot", $this) === false) {
            return false;
        }

//        $this->oldRouteMatching($container, $uri;

        /**
         * If the router doesn't return a valid module we use the default module
         */
//        $moduleName = $router->getModuleName() ?: ;
        $moduleName = $this->getModuleName();

        if (!$moduleName) {
            $moduleName = $this->defaultModule;
        }

        $moduleObject = null;

        /**
         * Process the module definition
         */
        if ($moduleName) {
            if (is_object($eventsManager)) {
                if ($eventsManager->fire("application:beforeStartModule", $this, $moduleName) === false) {
                    return false;
                }
            }

            /**
             * Gets the module definition
             */
            $module = $this->getModule($moduleName);

            /**
             * A module definition must be an array or an object
             */
            if (!is_array($module) && !is_object($module)) {
                throw new Exception("Invalid module definition");
            }

            /**
             * An array module definition contains a path to a module definition
             * class
             */
            if (is_array($module)) {
                /**
                 * Class name used to load the module definition
                 */
                if (isset($module["className"])) {
                    $className = $module["className"];
                } else {
                    $className = "Module";
                }

                /**
                 * If developer specify a path try to include the file
                 */
                if (isset($module["path"])) {
                    $path = $module["path"];

                    if (!file_exists($path)) {
                        throw new Exception(
                            "Module definition path '" . $path . "' doesn't exist"
                        );
                    }

                    if (!class_exists($className, false)) {
                        require_once $path;
                    }
                }

                $moduleObject = $container->get($className);

                /**
                 * 'registerAutoloaders' and 'registerServices' are
                 * automatically called
                 */
                $moduleObject->registerAutoloaders($container);
                $moduleObject->registerServices($container);
            } else {
                /**
                 * A module definition object, can be a Closure instance
                 */
                if (!($module instanceof Closure)) {
                    throw new Exception("Invalid module definition");
                }

                $moduleObject = call_user_func_array(
                    $module,
                    [
                        $container
                    ]
                );
            }

            /**
             * Calling afterStartModule event
             */
            if (is_object($eventsManager)) {
                $eventsManager->fire("application:afterStartModule", $this, $moduleObject);
            }
        }

        /**
         * Check whether use implicit views or not
         */
        $implicitView = $this->implicitView;

        if ($implicitView === true) {
            $view = $container->getShared("view");
        }

        /**
         * We get the parameters from the router and assign them to the dispatcher
         * Assign the values passed from the router
         */
        $dispatcher = $container->getShared("dispatcher");

        if (is_callable($this->dispatcherPrepareCallback)) {
            call_user_func($this->dispatcherPrepareCallback, $dispatcher);
        } else {
            $dispatcher->setModuleName($router->getModuleName());
            $dispatcher->setNamespaceName($router->getNamespaceName());
            $dispatcher->setControllerName($router->getControllerName());
            $dispatcher->setActionName($router->getActionName());
            $dispatcher->setParams($router->getParams());
        }

        /**
         * Start the view component (start output buffering)
         */
        if ($implicitView === true) {
            $view->start();
        }

        /**
         * Calling beforeHandleRequest
         */
        if (is_object($eventsManager)) {
            if ($eventsManager->fire("application:beforeHandleRequest", $this, $dispatcher) === false) {
                return false;
            }
        }

        /**
         * The dispatcher must return an object
         */
        $controller = $dispatcher->dispatch();

        /**
         * Get the latest value returned by an action
         */
        $possibleResponse = $dispatcher->getReturnedValue();

        /**
         * Returning false from an action cancels the view
         */
        if ($possibleResponse === false) {
            $response = $container->getShared("response");
        } else {
            /**
             * Returning a string makes use it as the body of the response
             */
            if (is_string($possibleResponse)) {
                $response = $container->getShared("response");

                $response->setContent($possibleResponse);
            } else {
                /**
                 * Check if the returned object is already a response
                 */
                $returnedResponse = (is_object($possibleResponse) && ($possibleResponse instanceof ResponseInterface));

                /**
                 * Calling afterHandleRequest
                 */
                if (is_object($eventsManager)) {
                    $eventsManager->fire("application:afterHandleRequest", $this, $controller);
                }

                /**
                 * If the dispatcher returns an object we try to render the view
                 * in auto-rendering mode
                 */
                if ($returnedResponse === false && $implicitView === true) {
                    if (is_object($controller)) {
                        $renderStatus = true;

                        /**
                         * This allows to make a custom view render
                         */
                        if (is_object($eventsManager)) {
                            $renderStatus = $eventsManager->fire("application:viewRender", $this, $view);
                        }

                        /**
                         * Check if the view process has been treated by the
                         * developer
                         */
                        if ($renderStatus !== false) {

                            /**
                             * Automatic render based on the latest controller
                             * executed
                             */
                            $view->render(
                                $dispatcher->getControllerName(),
                                $dispatcher->getActionName()
                            );
                        }
                    }
                }

                /**
                 * Finish the view component (stop output buffering)
                 */
                if ($implicitView === true) {
                    $view->finish();
                }

                if ($returnedResponse === true) {
                    /**
                     * We don't need to create a response because there is one
                     * already created
                     */
                    $response = $possibleResponse;
                } else {
                    $response = $container->getShared("response");

                    if ($implicitView === true) {
                        /**
                         * The content returned by the view is passed to the
                         * response service
                         */
                        $response->setContent(
                            $view->getContent()
                        );
                    }
                }
            }
        }

        /**
         * Calling beforeSendResponse
         */
        if (is_object($eventsManager)) {
            $eventsManager->fire("application:beforeSendResponse", $this, $response);
        }

        /**
         * Check whether send headers or not (by default yes)
         */
        if ($this->sendHeaders) {
            $response->sendHeaders();
        }

        /**
         * Check whether send cookies or not (by default yes)
         */
        if ($this->sendCookies) {
            $response->sendCookies();
        }

        /**
         * Return the response
         */
        return $response;
    }

    /**
     * Enables or disables sending cookies by each request handling
     *
     * @param bool $sendCookies
     * @return Application
     */
    public function sendCookiesOnHandleRequest(bool $sendCookies): Application
    {
        $this->sendCookies = $sendCookies;

        return $this;
    }


    /**
     * Enables or disables sending headers by each request handling
     *
     * @param bool $sendHeaders
     * @return Application
     */
    public function sendHeadersOnHandleRequest(bool $sendHeaders): Application
    {
        $this->sendHeaders = $sendHeaders;

        return $this;
    }

    /**
     * By default. The view is implicitly buffering all the output
     * You can full disable the view component using this method
     *
     * @param bool $implicitView
     * @return Application
     */
    public function useImplicitView(bool $implicitView): Application
    {
        $this->implicitView = $implicitView;

        return $this;
    }

    public function prepareDispatcherWithRoute(Closure $callable) {
        $this->dispatcherPrepareCallback = $callable;
    }

    private function oldRouteMatching($container, $uri)
    {
        $router = $container->getShared("router");

        /**
         * Handle the URI pattern (if any)
         */
        $router->handle($uri);

        /**
         * If a 'match' callback was defined in the matched route
         * The whole dispatcher+view behavior can be overridden by the developer
         */
        $matchedRoute = $router->getMatchedRoute();

        if (is_object($matchedRoute)) {
            $match = $matchedRoute->getMatch();

            if ($match !== null) {
                if ($match instanceof Closure) {
                    $match = Closure::bind($match, $container);
                }

                /**
                 * Directly call the match callback
                 */
                $possibleResponse = call_user_func_array(
                    $match,
                    $router->getParams()
                );

                /**
                 * If the returned value is a string return it as body
                 */
                if (is_string($possibleResponse)) {
                    $response = $container->getShared("response");

                    $response->setContent($possibleResponse);

                    return $response;
                }

                /**
                 * If the returned string is a ResponseInterface use it as
                 * response
                 */
                if (is_object($possibleResponse) && $possibleResponse instanceof ResponseInterface) {
                    $possibleResponse->sendHeaders();
                    $possibleResponse->sendCookies();

                    return $possibleResponse;
                }
            }
        }
    }

    public function getModuleName()
    {
        return $this->routedModuleName;
    }

    public function setModuleName(string $name)
    {
        $this->routedModuleName = $name;
        return $this;
    }
}
