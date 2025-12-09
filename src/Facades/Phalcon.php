<?php

namespace Vahalik\LaravelPhalcon\Facades;

use Illuminate\Support\Facades\Facade;

class Phalcon extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'phalcon.service';
    }
}
