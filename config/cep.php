<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default CEP Storage Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "array", "database"
    |
    */
    'default_store' => env('CEP_STORE', 'database'),

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'default'),
            'alerts_table' => 'nf_cep_alerts',
            'state_table' => 'nf_cep_state_snapshots',
            'metrics_table' => 'nf_cep_metrics',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Watermark & Late Event Policies
    |--------------------------------------------------------------------------
    */
    'watermark' => [
        'allowed_lateness_seconds' => (float) env('CEP_ALLOWED_LATENESS', 5.0),
        'late_event_strategy' => env('CEP_LATE_STRATEGY', 'drop'), // 'drop', 'side_output', 'allowed_lateness'
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Integrations
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'imdg_bridge' => (bool) env('CEP_IMDG_BRIDGE', true),
        'workflow_bridge' => (bool) env('CEP_WORKFLOW_BRIDGE', true),
        'event_bus_bridge' => (bool) env('CEP_EVENT_BUS_BRIDGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Alerting
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'level' => 'INFO',
        'channel' => 'cep',
    ],
];
