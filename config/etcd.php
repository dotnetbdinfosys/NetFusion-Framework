<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

return [
    /*
    |--------------------------------------------------------------------------
    | etcd v3 MVCC Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the enterprise distributed key-value store,
    | MVCC revision tracking, lease management, and event streaming.
    |
    */

    'default' => env('ETCD_DRIVER', 'memory'),

    'drivers' => [
        'memory' => [
            'class' => \NetFusion\Core\Etcd\Storage\MvccStore::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lease Management
    |--------------------------------------------------------------------------
    |
    | Default TTL and cleanup heartbeat intervals for distributed leases.
    |
    */
    'lease' => [
        'default_ttl'      => (int) env('ETCD_LEASE_TTL', 60),
        'cleanup_interval' => (int) env('ETCD_LEASE_CLEANUP_INTERVAL', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compaction & Retention
    |--------------------------------------------------------------------------
    |
    | Automatically discard historical revisions older than retention window.
    |
    */
    'compaction' => [
        'auto_compact'         => (bool) env('ETCD_AUTO_COMPACT', false),
        'retention_revisions'  => (int) env('ETCD_RETENTION_REVISIONS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_txn_ops'       => 128,
        'max_request_bytes' => 1572864, // 1.5 MB
    ],
];
