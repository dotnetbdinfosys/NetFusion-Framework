<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Edge Computing & Serverless Worker Configuration
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Pre-Warmed V8 Isolate Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Controls sub-millisecond cold start spare isolates and maximum concurrency.
    |
    */
    'pool' => [
        'min_spare' => 2,
        'max_capacity' => 64,
        'default_memory_mb' => 128,
        'default_cpu_timeout_ms' => 50,
        'max_invocations_per_isolate' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Edge KV Cache Settings
    |--------------------------------------------------------------------------
    |
    | Controls default edge response caching TTL and max entries.
    |
    */
    'cache' => [
        'enabled' => true,
        'default_ttl' => 300, // seconds
        'max_entries' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Point-of-Presence (PoP) & Geo Configuration
    |--------------------------------------------------------------------------
    */
    'default_colo' => 'DHK', // Dhaka edge node
    'default_country' => 'BD',
];
