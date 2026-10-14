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
    | Distributed Event Streaming Platform (Kafka Architecture)
    |--------------------------------------------------------------------------
    */

    'default' => env('KAFKA_DRIVER', 'memory'),

    'topics' => [
        'default_partitions'         => (int) env('KAFKA_DEFAULT_PARTITIONS', 1),
        'default_replication_factor' => (int) env('KAFKA_DEFAULT_REPLICATION_FACTOR', 1),
        'auto_create_topics'         => (bool) env('KAFKA_AUTO_CREATE_TOPICS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commit Log & Segment Retention
    |--------------------------------------------------------------------------
    */
    'log' => [
        'segment_bytes'  => 10485760, // 10 MB
        'retention_ms'   => 604800000, // 7 days
        'retention_bytes' => -1,        // Unlimited
        'cleanup_policy' => 'delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Producer Defaults
    |--------------------------------------------------------------------------
    */
    'producer' => [
        'acks'       => env('KAFKA_PRODUCER_ACKS', 'all'),
        'retries'    => 3,
        'batch_size' => 16384,
        'linger_ms'  => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer & Group Coordinator Defaults
    |--------------------------------------------------------------------------
    */
    'consumer' => [
        'group_id'           => env('KAFKA_CONSUMER_GROUP', 'netfusion-group'),
        'auto_offset_reset'  => env('KAFKA_AUTO_OFFSET_RESET', 'earliest'),
        'enable_auto_commit' => true,
        'session_timeout_ms' => 30000,
        'isolation_level'    => 'read_uncommitted',
    ],
];
