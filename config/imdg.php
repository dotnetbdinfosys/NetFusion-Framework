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
    | Default In-Memory Data Grid Cluster
    |--------------------------------------------------------------------------
    */
    'default' => env('IMDG_CLUSTER', 'default'),

    'clusters' => [
        'default' => [
            'name' => env('IMDG_CLUSTER_NAME', 'netfusion-grid-production'),
            'node_id' => env('IMDG_NODE_ID', 'node-local-1'),
            'host' => env('IMDG_HOST', '127.0.0.1'),
            'port' => (int) env('IMDG_PORT', 5701),
            'partitions' => (int) env('IMDG_PARTITIONS', 271),
            'backups' => (int) env('IMDG_BACKUPS', 1),
            'store' => env('IMDG_STORE', 'array'), // "array", "database"
            'write_mode' => env('IMDG_WRITE_MODE', 'none'), // "none", "write-through", "write-behind"

            // Multi-Tier L1 Near-Cache Configuration
            'near_cache' => [
                'enabled' => env('IMDG_NEAR_CACHE_ENABLED', true),
                'max_size' => (int) env('IMDG_NEAR_CACHE_MAX_SIZE', 10000),
                'ttl' => (int) env('IMDG_NEAR_CACHE_TTL', 300),
                'tti' => (int) env('IMDG_NEAR_CACHE_TTI', 60),
                'eviction' => env('IMDG_NEAR_CACHE_EVICTION', 'lru'), // "lru", "lfu", "fifo"
                'invalidate_on_change' => true,
            ],

            // Seed discovery members
            'members' => [
                ['id' => 'node-local-1', 'host' => '127.0.0.1', 'port' => 5701, 'weight' => 1],
            ],
        ],
    ],
];
