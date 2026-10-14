<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework - Distributed Lock Configuration
|--------------------------------------------------------------------------
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default Lock Driver
    |--------------------------------------------------------------------------
    | Supported: "database", "file", "array"
    */
    'default' => env('LOCK_DRIVER', 'database'),

    'table' => 'netfusion_locks',

    'storage_path' => storage_path('framework/locks'),

    'default_ttl' => 60,
];