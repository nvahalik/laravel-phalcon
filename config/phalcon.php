<?php

// Config for the Vahalik/LaravelPhalcon package.
return [

    /*
    |--------------------------------------------------------------------------
    | Module Definitions
    |--------------------------------------------------------------------------
    |
    | Define your application's module configurations in this array. You can
    | add as many modules as needed, with each module having its respective
    | class name and associated configurations.
    |
    | Example:
    | 'modules' => [
    |     'module' => [
    |         'className' => 'YourApp\\Module',
    |     ],
    | ],
    |
    */
    'modules' => [],


    /*
    |--------------------------------------------------------------------------
    | Autoload Path
    |--------------------------------------------------------------------------
    |
    | If set, this autoloader will be included before processing the routes.
    |
    */
    'autoload_path' => '',

    /*
    |--------------------------------------------------------------------------
    | Runtime Behavior
    |--------------------------------------------------------------------------
    |
    | These options control how the runtime behavior of the application should
    | operate.
    |
    */
    'runtime' => [

        /*
        |--------------------------------------------------------------------------
        | Ignore Dynamic Controllers
        |--------------------------------------------------------------------------
        |
        | If set to true, the application will ignore and not import fully dynamic
        | controllers. (e.g. dynamic :controller or :action routes.)
        |
        */
        'ignore_dynamic_controllers' => true,

        /*
        |--------------------------------------------------------------------------
        | Skip Missing Controllers
        |--------------------------------------------------------------------------
        |
        | When enabled, the application will skip over any missing or
        | unregistered controllers instead of throwing exceptions. This can
        | be useful in large applications where controllers may be old or missing.
        |
        */
        'skip_missing_controllers' => true,
    ],

];
