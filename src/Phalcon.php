<?php

namespace Vahalik\LaravelPhalcon;

use Closure;
use Vahalik\LaravelPhalcon\Phalcon\Application;

class Phalcon
{
    protected $beforeMiddlewareCallback = null;

    protected $afterMiddlewareCallback = null;

    protected $beforeRoutingCallback = null;

    public function beforeMiddlwareCallback(null|Closure $closure)
    {
        $this->beforeMiddlewareCallback = $closure;

        return $this;
    }

    public function postMiddlwareCallback(null|Closure $closure)
    {
        $this->afterMiddlewareCallback = $closure;

        return $this;
    }

    public function runPreMiddlewareCallback()
    {
        if (! $this->beforeMiddlewareCallback) {
            return;
        }

        call_user_func($this->beforeMiddlewareCallback);
    }

    public function runPostMiddlewareCallback()
    {
        if (! $this->afterMiddlewareCallback) {
            return;
        }

        call_user_func($this->afterMiddlewareCallback);
    }

    public function beforeRouting(Closure|null $closure = null) {
        $this->beforeRoutingCallback = $closure;

        return $this;
    }

    public function runBeforeRouting(\Phalcon\Di\DiInterface $di, Application $application) {
        if (! $this->beforeRoutingCallback) {
            return;
        }

        call_user_func($this->beforeRoutingCallback, $di, $application);
    }
}
