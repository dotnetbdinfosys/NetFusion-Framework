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
    | Default TSDB Driver
    |--------------------------------------------------------------------------
    | Options: 'database', 'array'
    */
    'driver' => env('TSDB_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (in seconds)
    |--------------------------------------------------------------------------
    | Raw data retention (e.g. 7 days = 604800s)
    | Rollup data retention (e.g. 90 days = 7776000s)
    */
    'retention' => [
        'raw_seconds' => env('TSDB_RAW_RETENTION', 604800),
        'rollup_seconds' => env('TSDB_ROLLUP_RETENTION', 7776000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Downsampling Rollup Windows
    |--------------------------------------------------------------------------
    | Standard aggregation windows (in seconds): 1m (60), 5m (300), 1h (3600), 1d (86400)
    */
    'rollup_windows' => [60, 300, 3600, 86400],

    /*
    |--------------------------------------------------------------------------
    | Anomaly Detection Defaults
    |--------------------------------------------------------------------------
    */
    'anomaly' => [
        'default_method' => env('ANOMALY_METHOD', 'zscore'),
        'zscore_threshold' => 3.0,
        'iqr_multiplier' => 1.5,
        'ewma_alpha' => 0.3,
    ],
];
