<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework Configuration
|--------------------------------------------------------------------------
|
| File      : config/rpc.php
| Module    : RPC & Service Mesh
| Purpose   : Microservices RPC, Service Discovery, Load Balancing & Circuit Breaker config.
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
    | RPC Server Default Daemon Settings
    |--------------------------------------------------------------------------
    */
    'host' => env('RPC_HOST', '0.0.0.0'),
    'port' => (int) env('RPC_PORT', 9500),

    /*
    |--------------------------------------------------------------------------
    | Default RPC Client Timeout & Retries
    |--------------------------------------------------------------------------
    */
    'timeout' => (float) env('RPC_TIMEOUT', 5.0),
    'retries' => (int) env('RPC_RETRIES', 0),

    /*
    |--------------------------------------------------------------------------
    | Service Mesh Load Balancer Algorithm
    |--------------------------------------------------------------------------
    | Supported: "round_robin", "random", "least_connections"
    */
    'load_balancer' => env('RPC_LOAD_BALANCER', 'round_robin'),

    /*
    |--------------------------------------------------------------------------
    | Service Discovery Registry Driver
    |--------------------------------------------------------------------------
    | Supported: "array", "database", "file"
    */
    'registry' => [
        'driver'   => env('RPC_REGISTRY_DRIVER', 'array'),
        'ttl'      => (int) env('RPC_REGISTRY_TTL', 30),
        'table'    => 'netfusion_service_registry',
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Global Settings
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold'            => (int) env('CB_FAILURE_THRESHOLD', 5),
        'reset_timeout_seconds'        => (float) env('CB_RESET_TIMEOUT', 30.0),
        'half_open_success_threshold'  => (int) env('CB_HALF_OPEN_SUCCESS', 2),
    ],

];
