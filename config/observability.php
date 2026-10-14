<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Service Information
    |--------------------------------------------------------------------------
    */
    'service_name' => env('OTEL_SERVICE_NAME', 'netfusion-service'),
    'service_version' => env('OTEL_SERVICE_VERSION', '1.0.0'),
    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Tracing & Sampling
    |--------------------------------------------------------------------------
    */
    'tracing' => [
        'enabled' => env('OTEL_TRACES_ENABLED', true),
        'sample_rate' => (float) env('OTEL_SAMPLE_RATE', 1.0), // 1.0 = 100%
        'max_buffered_spans' => (int) env('OTEL_BUFFER_CAPACITY', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'enabled' => env('OTEL_METRICS_ENABLED', true),
        'prometheus_scraping' => env('PROMETHEUS_ENABLED', true),
        'default_histogram_boundaries' => [5.0, 10.0, 25.0, 50.0, 100.0, 250.0, 500.0, 1000.0, 2500.0, 5000.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiler Configuration
    |--------------------------------------------------------------------------
    */
    'profiler' => [
        'enabled' => env('OTEL_PROFILER_ENABLED', true),
        'max_samples' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exporter Endpoints
    |--------------------------------------------------------------------------
    */
    'exporters' => [
        'otlp_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
        'zipkin_endpoint' => env('OTEL_EXPORTER_ZIPKIN_ENDPOINT', 'http://localhost:9411/api/v2/spans'),
    ],
];
