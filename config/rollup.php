<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup Configuration
 *
 * @package    NetFusion\Config
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Sparse Merkle Tree (SMT) Depth
    |--------------------------------------------------------------------------
    | Default tree depth: 16 supports 65,536 leaves; 32 supports 4.29 billion.
    */
    'tree_depth' => (int) env('ROLLUP_TREE_DEPTH', 16),

    /*
    |--------------------------------------------------------------------------
    | Maximum Transitions Per Batch
    |--------------------------------------------------------------------------
    | The maximum number of transactions bundled into a single ZK rollup batch.
    */
    'batch_size' => (int) env('ROLLUP_BATCH_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Hashing Algorithm
    |--------------------------------------------------------------------------
    | Cryptographic hash algorithm utilized across tree nodes and MMR peaks.
    */
    'hash_algorithm' => env('ROLLUP_HASH_ALGO', 'sha256'),

    /*
    |--------------------------------------------------------------------------
    | Proof Scheme
    |--------------------------------------------------------------------------
    | Attestation verification scheme ('Groth16/Plonk-Attestation').
    */
    'proof_scheme' => 'Groth16/Plonk-Attestation',

    /*
    |--------------------------------------------------------------------------
    | Storage Persistence
    |--------------------------------------------------------------------------
    | Database table references for persistent ledger archiving.
    */
    'tables' => [
        'accounts'    => 'rollup_accounts',
        'batches'     => 'rollup_batches',
        'transitions' => 'rollup_transitions',
        'mmr_nodes'   => 'rollup_mmr_nodes',
    ],
];
