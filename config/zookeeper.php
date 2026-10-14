<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - ZooKeeper Configuration
 * |--------------------------------------------------------------------------
 * |
 * | Configuration settings for Distributed Coordination Service & Consensus
 * | Key-Value Store (Apache ZooKeeper Architecture).
 * |
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Server Cluster Identification
    |--------------------------------------------------------------------------
    */
    'server_id' => (int)env('ZOOKEEPER_SERVER_ID', 1),
    'cluster_size' => (int)env('ZOOKEEPER_CLUSTER_SIZE', 3),
    'tick_time' => (int)env('ZOOKEEPER_TICK_TIME', 2000), // ms
    'init_limit' => (int)env('ZOOKEEPER_INIT_LIMIT', 10), // ticks
    'sync_limit' => (int)env('ZOOKEEPER_SYNC_LIMIT', 5),  // ticks

    /*
    |--------------------------------------------------------------------------
    | Client Session Defaults
    |--------------------------------------------------------------------------
    */
    'session_timeout' => (int)env('ZOOKEEPER_SESSION_TIMEOUT', 30000), // ms
    'max_client_connections' => (int)env('ZOOKEEPER_MAX_CLIENT_CNXNS', 60),

    /*
    |--------------------------------------------------------------------------
    | Snapshot & Transaction Log Persistence
    |--------------------------------------------------------------------------
    */
    'data_dir' => storage_path('zookeeper/data'),
    'data_log_dir' => storage_path('zookeeper/logs'),
];
