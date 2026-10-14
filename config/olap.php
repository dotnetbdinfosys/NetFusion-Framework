<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - OLAP Analytics Configuration
 * |--------------------------------------------------------------------------
 * |
 * | Configuration settings for Distributed Real-Time OLAP Analytical Engine
 * | (Apache Druid & ClickHouse Architecture).
 * |
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Analytical Storage Settings
    |--------------------------------------------------------------------------
    */
    'default_datasource' => env('ANALYTICS_DEFAULT_DATASOURCE', 'events'),
    'max_rows_in_memory' => (int)env('ANALYTICS_MAX_ROWS_IN_MEMORY', 5000),
    'segment_granularity' => env('ANALYTICS_SEGMENT_GRANULARITY', 'day'),
    'query_granularity' => env('ANALYTICS_QUERY_GRANULARITY', 'minute'),
    'rollup' => (bool)env('ANALYTICS_ROLLUP', true),

    /*
    |--------------------------------------------------------------------------
    | Historical Segment Deep Storage Path
    |--------------------------------------------------------------------------
    */
    'deep_storage' => [
        'driver' => env('ANALYTICS_STORAGE_DRIVER', 'database'), // 'database' or 'local'
        'path' => storage_path('analytics/segments'),
    ],
];
