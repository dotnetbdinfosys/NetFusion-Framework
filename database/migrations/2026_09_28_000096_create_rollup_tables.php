<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000096_create_rollup_tables.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $conn): void
    {
        $pdo = $conn->getPdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS rollup_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id VARCHAR(191) NOT NULL UNIQUE,
            public_key VARCHAR(191) NOT NULL,
            balance REAL NOT NULL DEFAULT 0.0,
            nonce INTEGER NOT NULL DEFAULT 0,
            metadata TEXT,
            state_hash VARCHAR(64) NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rollup_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER NOT NULL UNIQUE,
            previous_state_root VARCHAR(64) NOT NULL,
            new_state_root VARCHAR(64) NOT NULL,
            transitions_count INTEGER NOT NULL,
            batch_hash VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            validity_proof TEXT,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rollup_transitions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            transition_id VARCHAR(191) NOT NULL UNIQUE,
            batch_id INTEGER,
            transition_type VARCHAR(32) NOT NULL,
            sender VARCHAR(191) NOT NULL,
            receiver VARCHAR(191) NOT NULL,
            amount REAL NOT NULL,
            nonce INTEGER NOT NULL,
            signature VARCHAR(191),
            payload_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rollup_mmr_nodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            leaf_index INTEGER NOT NULL UNIQUE,
            leaf_hash VARCHAR(64) NOT NULL,
            bagged_root VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rollup_transitions_batch ON rollup_transitions (batch_id)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS rollup_mmr_nodes");
        $pdo->exec("DROP TABLE IF EXISTS rollup_transitions");
        $pdo->exec("DROP TABLE IF EXISTS rollup_batches");
        $pdo->exec("DROP TABLE IF EXISTS rollup_accounts");
    }
};
