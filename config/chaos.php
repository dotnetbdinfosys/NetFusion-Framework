<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chaos Engineering Master Switch
    |--------------------------------------------------------------------------
    | Global kill switch and driver configuration.
    */
    'enabled' => env('CHAOS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Blast Radius Constraints
    |--------------------------------------------------------------------------
    | Safe operating boundaries for fault injection.
    */
    'guardrails' => [
        'allowed_environments' => ['development', 'testing', 'staging'],
        'max_affected_traffic_percent' => (float) env('CHAOS_MAX_AFFECTED_PERCENT', 10.0),
        'max_duration_seconds' => (int) env('CHAOS_MAX_DURATION_SECONDS', 300),
        'required_probe_header' => 'X-Chaos-Probe',
        'excluded_routes' => [
            '/health',
            '/metrics',
            '/readiness',
            '/liveness',
            '/auth/logout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Autonomous Chaos Monkey Configuration
    |--------------------------------------------------------------------------
    | Configuration for automated randomized attacks during maintenance windows.
    */
    'monkey' => [
        'enabled' => env('CHAOS_MONKEY_ENABLED', false),
        'allowed_days' => [1, 2, 3, 4, 5], // Monday through Friday
        'start_hour' => 9,                 // 09:00 AM
        'end_hour' => 17,                  // 05:00 PM
    ],

    /*
    |--------------------------------------------------------------------------
    | Steady-State Health Thresholds
    |--------------------------------------------------------------------------
    */
    'steady_state' => [
        'max_error_rate_percent' => 5.0,
        'max_p99_latency_ms' => 1000.0,
        'min_success_rate_percent' => 95.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Kill Switch Lock File
    |--------------------------------------------------------------------------
    */
    'kill_switch_path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'netfusion_chaos_kill_switch.lock',
];
