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
    | Default Scheduler Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone used when parsing cron expressions and checking
    | schedule deadlines.
    |
    */
    'timezone' => env('SCHEDULER_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Cluster Leader Election
    |--------------------------------------------------------------------------
    |
    | When running multiple nodes in high availability or microservices,
    | only the elected cluster leader executes scheduled tasks.
    |
    */
    'cluster' => [
        'enabled' => env('SCHEDULER_CLUSTER_ENABLED', true),
        'ttl_seconds' => (int)env('SCHEDULER_LEADER_TTL', 30),
        'heartbeat_interval' => (int)env('SCHEDULER_HEARTBEAT_INTERVAL', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Task Settings
    |--------------------------------------------------------------------------
    |
    | Default execution timeout and misfire strategy for scheduled jobs.
    |
    */
    'defaults' => [
        'timeout' => (int)env('SCHEDULER_DEFAULT_TIMEOUT', 60),
        'misfire_policy' => env('SCHEDULER_MISFIRE_POLICY', 'FIRE_NOW'), // FIRE_NOW, IGNORE, RESCHEDULE_NEXT
        'without_overlapping' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage & Telemetry Retention
    |--------------------------------------------------------------------------
    |
    | Database table configurations and execution log retention periods.
    |
    */
    'storage' => [
        'driver' => env('SCHEDULER_STORAGE_DRIVER', 'database'), // database, memory
        'retention_days' => (int)env('SCHEDULER_RETENTION_DAYS', 30),
    ],
];
