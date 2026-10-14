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
    | Default Node Identifier
    |--------------------------------------------------------------------------
    |
    | Unique node identifier for local replica mutations in the cluster.
    |
    */
    'node_id' => env('CRDT_NODE_ID', 'node_' . gethostname()),

    /*
    |--------------------------------------------------------------------------
    | Default CRDT Data Type
    |--------------------------------------------------------------------------
    |
    | Supported: "rga_text", "pn_counter", "or_set", "lww_register", "json_crdt"
    |
    */
    'default_type' => 'rga_text',

    /*
    |--------------------------------------------------------------------------
    | Delta Compression
    |--------------------------------------------------------------------------
    |
    | When enabled, wire synchronizer messages use deflate compression to minimize
    | bandwidth over WebSocket / SSE transports.
    |
    */
    'compression_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Snapshot Threshold
    |--------------------------------------------------------------------------
    |
    | Number of operations or mutations after which a persistent state snapshot
    | should be written to the database.
    |
    */
    'auto_snapshot_threshold' => 100,
];
