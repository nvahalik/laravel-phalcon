<?php

namespace Vahalik\LaravelPhalcon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PhalconCompatability
{
    public function handle(Request $request, Closure $next): Response
    {
        define('APP_PATH', base_path('phalcon/src'));
        $di = require(base_path('phalcon/config/di.php'));
        \Phalcon\Di\Di::setDefault($di);

        return $next($request);
    }
}
