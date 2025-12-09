<?php

namespace Vahalik\LaravelPhalcon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vahalik\LaravelPhalcon\Facades\Phalcon;

class PhalconCompatability
{
    public function handle(Request $request, Closure $next): Response
    {
        Phalcon::runPreMiddlewareCallback();

        return $next($request);
    }
}
