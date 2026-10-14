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

/*
|--------------------------------------------------------------------------
| NetFusion Framework Configuration
|--------------------------------------------------------------------------
|
| File      : config/telemetry.php
| Module    : OpenTelemetry Distributed Tracing, Metrics & Prometheus
| Purpose   : Metric collection buckets, exporters and scrape endpoints.
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
    | Telemetry & Metrics Service Status
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool)env('TELEMETRY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Exporter Driver
    |--------------------------------------------------------------------------
    | Supported: "prometheus", "json", "otlp"
    */
    'default_exporter' => env('TELEMETRY_EXPORTER', 'prometheus'),

    /*
    |--------------------------------------------------------------------------
    | Prometheus Scraping Endpoint
    |--------------------------------------------------------------------------
    | Exposes /metrics for Prometheus scraping
    */
    'prometheus' => [
        'path' => env('PROMETHEUS_METRICS_PATH', '/metrics'),
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Protocol (OTLP) Collector
    |--------------------------------------------------------------------------
    */
    'otlp' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318/v1/metrics'),
        'headers'  => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Histogram Latency Buckets (in seconds)
    |--------------------------------------------------------------------------
    */
    'histogram_buckets' => [
        'default' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        'http'    => [0.005, 0.01, 0.025, 0.05, 0.1, 0.2, 0.5, 1.0, 2.0, 5.0],
        'db'      => [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0],
    ],
];
