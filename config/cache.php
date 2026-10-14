<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/cache.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Cache stores, driver settings, and key prefix configuration.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | Specifies the default cache connection store to use throughout the application.
    | Supported: "array", "file", "database"
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Defines configuration parameters for each supported cache driver.
    |
    */
    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache/data'),
        ],

        'database' => [
            'driver'     => 'database',
            'table'      => 'netfusion_cache',
            'connection' => null, // null defaults to default database connection
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | A unique string prepended to all cache keys to avoid collision.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'netfusion_cache_'),

];
