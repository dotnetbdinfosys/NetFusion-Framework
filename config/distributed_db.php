<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
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

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Raft Consensus Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Defines default Raft nodes, heartbeat intervals, and election timeouts.
    |
    */
    'raft' => [
        'default_cluster' => 'default',
        'heartbeat_interval_ms' => 150,
        'election_timeout_min_ms' => 300,
        'election_timeout_max_ms' => 600,
        'nodes' => [
            ['id' => 'node-1', 'host' => '127.0.0.1', 'port' => 8001],
            ['id' => 'node-2', 'host' => '127.0.0.1', 'port' => 8002],
            ['id' => 'node-3', 'host' => '127.0.0.1', 'port' => 8003],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geo Multi-Master Replication Configuration
    |--------------------------------------------------------------------------
    |
    | Defines default replication synchronization mode and conflict strategy.
    | Modes: ASYNC, SYNC_QUORUM, SEMI_SYNC
    | Strategies: LAST_WRITE_WINS, VECTOR_CLOCK, CRDT_MERGE, CUSTOM_CALLABLE
    |
    */
    'multi_master' => [
        'mode' => 'ASYNC',
        'conflict_strategy' => 'LAST_WRITE_WINS',
        'default_region' => 'us-east-1',
        'regions' => [
            'us-east-1' => ['name' => 'US East (N. Virginia)', 'latency_weight' => 1.0],
            'eu-west-1' => ['name' => 'Europe (Ireland)', 'latency_weight' => 1.2],
            'ap-southeast-1' => ['name' => 'Asia Pacific (Singapore)', 'latency_weight' => 1.5],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed SQL & Sharding Topologies
    |--------------------------------------------------------------------------
    |
    | Table sharding schemas, partition strategies (HASH, RANGE, LIST, BROADCAST).
    |
    */
    'distributed_sql' => [
        'default_partition_strategy' => 'HASH',
        'shards_per_table' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed Transaction & Lock Leases
    |--------------------------------------------------------------------------
    */
    'transactions' => [
        '2pc_prepare_timeout_ms' => 3000,
        'default_lock_ttl_ms' => 5000,
    ],
];
