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
    | Local Region Identifier
    |--------------------------------------------------------------------------
    */
    'local_region' => env('REPLICATION_LOCAL_REGION', 'us-east'),

    /*
    |--------------------------------------------------------------------------
    | Multi-Region Edge Cluster Topology
    |--------------------------------------------------------------------------
    */
    'regions' => [
        'us-east' => [
            'name' => 'North America East (Virginia)',
            'endpoint' => env('REGION_US_EAST_URL', 'https://us-east.edge.netfusion.io'),
            'countries' => ['US', 'CA', 'MX'],
            'weight' => 100,
            'latency_ms' => 12.5,
        ],
        'eu-west' => [
            'name' => 'Europe West (Frankfurt)',
            'endpoint' => env('REGION_EU_WEST_URL', 'https://eu-west.edge.netfusion.io'),
            'countries' => ['DE', 'GB', 'FR', 'NL', 'IT', 'ES', 'SE'],
            'weight' => 100,
            'latency_ms' => 24.0,
        ],
        'ap-south' => [
            'name' => 'Asia Pacific South (Singapore)',
            'endpoint' => env('REGION_AP_SOUTH_URL', 'https://ap-south.edge.netfusion.io'),
            'countries' => ['BD', 'IN', 'SG', 'MY', 'JP', 'AU'],
            'weight' => 100,
            'latency_ms' => 38.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Replication Storage Driver
    |--------------------------------------------------------------------------
    | Supported: 'database', 'array'
    */
    'driver' => env('REPLICATION_STORE_DRIVER', 'database'),
    'table'  => 'netfusion_replication_changelog',

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'batch_size' => (int)env('REPLICATION_BATCH_SIZE', 100),
    'auto_sync'  => (bool)env('REPLICATION_AUTO_SYNC', true),
];
