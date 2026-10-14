<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Migration
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

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\Migrations\Migration;

/**
 * Migration: create_distributed_db_tables
 *
 * Tables:
 *   - distributed_raft_logs          : Persistent Raft consensus log entries
 *   - distributed_replication_stream : Cross-region multi-master replication mutations
 *   - distributed_conflict_history   : Reconciled multi-master mutation conflict records
 *   - distributed_locks              : Monotonic lease-based distributed locks
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS distributed_raft_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cluster_name VARCHAR(64) NOT NULL,
                node_id VARCHAR(64) NOT NULL,
                term INTEGER NOT NULL,
                log_index INTEGER NOT NULL,
                command VARCHAR(255) NOT NULL,
                payload TEXT NULL,
                entry_hash VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS distributed_replication_stream (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id VARCHAR(100) UNIQUE NOT NULL,
                table_name VARCHAR(120) NOT NULL,
                primary_key VARCHAR(120) NOT NULL,
                operation VARCHAR(20) NOT NULL,
                origin_region VARCHAR(64) NOT NULL,
                vector_clock TEXT NOT NULL,
                old_data TEXT NULL,
                new_data TEXT NULL,
                timestamp BIGINT NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS distributed_conflict_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_name VARCHAR(120) NOT NULL,
                primary_key VARCHAR(120) NOT NULL,
                reporting_region VARCHAR(64) NOT NULL,
                winning_origin VARCHAR(64) NOT NULL,
                strategy_used VARCHAR(40) NOT NULL,
                resolved_payload TEXT NOT NULL,
                resolved_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS distributed_locks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                resource_key VARCHAR(255) UNIQUE NOT NULL,
                owner_id VARCHAR(120) NOT NULL,
                fencing_token INTEGER NOT NULL,
                expires_at DOUBLE NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS distributed_locks");
        $db->statement("DROP TABLE IF EXISTS distributed_conflict_history");
        $db->statement("DROP TABLE IF EXISTS distributed_replication_stream");
        $db->statement("DROP TABLE IF EXISTS distributed_raft_logs");
    }
};
