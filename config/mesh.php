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
    | Service Mesh Identity & Trust Domain
    |--------------------------------------------------------------------------
    */
    'trust_domain' => env('MESH_TRUST_DOMAIN', 'cluster.local'),
    'local_service' => env('MESH_LOCAL_SERVICE', 'api-gateway'),
    'namespace' => env('MESH_NAMESPACE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    */
    'default_store' => env('MESH_STORE', 'database'),

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'default'),
            'services_table' => 'nf_mesh_services',
            'routes_table' => 'nf_mesh_routes',
            'policies_table' => 'nf_mesh_policies',
            'certs_table' => 'nf_mesh_certificates',
            'metrics_table' => 'nf_mesh_metrics',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & mTLS Policies
    |--------------------------------------------------------------------------
    */
    'security' => [
        'mtls_mode' => env('MESH_MTLS_MODE', 'STRICT'), // 'STRICT', 'PERMISSIVE', 'DISABLED'
        'cert_validity_seconds' => (int) env('MESH_CERT_VALIDITY', 86400),
        'auto_rotate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resilience & Outlier Detection Defaults
    |--------------------------------------------------------------------------
    */
    'resilience' => [
        'default_timeout_ms' => (int) env('MESH_TIMEOUT_MS', 5000),
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
            'recovery_timeout_seconds' => 30.0,
            'half_open_successes' => 3,
        ],
        'outlier_detection' => [
            'enabled' => true,
            'consecutive_5xx_threshold' => 5,
            'base_ejection_seconds' => 30.0,
            'max_ejection_percent' => 50,
        ],
    ],
];
