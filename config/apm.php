<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — APM Configuration
 |--------------------------------------------------------------------------
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

    'environment'   => env('APP_ENV', 'production'),
    'service_name'  => env('APM_SERVICE', 'netfusion'),

    'logging' => [
        'channel'    => env('APM_LOG_CHANNEL', 'apm'),
        'min_level'  => env('APM_LOG_LEVEL', 'info'),
        'output_file'=> env('APM_LOG_FILE', null),
    ],

    'tracing' => [
        'enabled'     => (bool) env('APM_TRACING_ENABLED', true),
        'sample_rate' => (float) env('APM_SAMPLE_RATE', 1.0),
    ],

    'slow_query' => [
        'threshold_ms' => (float) env('APM_SLOW_QUERY_MS', 100.0),
    ],

    'profiling' => [
        'enabled' => (bool) env('APM_PROFILING_ENABLED', false),
    ],

];
