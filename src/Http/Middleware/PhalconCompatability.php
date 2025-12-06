<?php

namespace Vahalik\LaravelPhalcon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PhalconCompatability
{
    public function handle(Request $request, Closure $next): Response
    {
        // @todo add a pre-load hook
        define('APP_PATH', base_path('/code/'));

        // Make these definable
        require '/code/lib/php/core/helpers.php';
        require '/code/lib/php/core/services.php';

        // @todo add a post-load hook
        \Phalcon\Di\Di::getDefault()->get('config');

        return $next($request);
    }
}
