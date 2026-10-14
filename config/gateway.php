<?php

/**
 * DotNetBD Enterprise Framework — Gateway Configuration
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Load Balancer Strategy
    |--------------------------------------------------------------------------
    | Supported: "round_robin", "weighted_round_robin", "least_connections",
    |            "ip_hash", "latency_aware"
    */
    'load_balancer' => 'round_robin',

    /*
    |--------------------------------------------------------------------------
    | Proxy Timeouts (seconds)
    |--------------------------------------------------------------------------
    */
    'connect_timeout' => 5,
    'read_timeout'    => 30,

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Settings
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,   // trips after N consecutive failures
        'cooldown_seconds'  => 30,  // time before attempting half-open probe
        'success_threshold' => 2,   // successes needed to close circuit
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Rate Limiting
    |--------------------------------------------------------------------------
    | strategy: "token_bucket" | "sliding_window"
    | dimension: "ip" | "api_key" | "user" | "tenant"
    */
    'rate_limit' => [
        'enabled'       => true,
        'strategy'      => 'token_bucket',
        'dimension'     => 'ip',
        'capacity'      => 60,     // max requests
        'refill_rate'   => 1.0,    // tokens/second
        'window'        => 60,     // window size (sliding_window only)
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Health Check Configuration
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'enabled'         => true,
        'path'            => '/health',
        'interval'        => 10,    // seconds between checks
        'timeout'         => 3,     // seconds per check
        'expected_status' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Defaults
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'enabled' => false,
        'origins' => ['*'],
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization', 'X-Api-Key'],
        'max_age' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled'       => true,
        'slow_threshold' => 1000,  // ms — requests above this are flagged
    ],

    /*
    |--------------------------------------------------------------------------
    | Simulate Mode (disables real HTTP proxy — for testing only)
    |--------------------------------------------------------------------------
    */
    'simulate' => false,
];
