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
 * Migration: create_iac_tables
 *
 * Tables:
 *   - iac_state_files    : Versioned Terraform v4 StateFiles
 *   - iac_locks          : Distributed state mutual exclusion locks
 *   - iac_execution_logs : Historical audit trail of planned and applied changes
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS iac_state_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace VARCHAR(100) NOT NULL,
                serial INTEGER NOT NULL,
                lineage VARCHAR(64) NOT NULL,
                state_json TEXT NOT NULL,
                checksum VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS iac_locks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lock_id VARCHAR(64) UNIQUE NOT NULL,
                workspace VARCHAR(100) NOT NULL,
                operation VARCHAR(50) NOT NULL,
                who VARCHAR(120) NOT NULL,
                version VARCHAR(32) NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS iac_execution_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_id VARCHAR(64) NOT NULL,
                workspace VARCHAR(100) NOT NULL,
                action VARCHAR(20) NOT NULL,
                resource_address VARCHAR(160) NOT NULL,
                status VARCHAR(30) NOT NULL,
                attributes_json TEXT NULL,
                executed_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS iac_execution_logs");
        $db->statement("DROP TABLE IF EXISTS iac_locks");
        $db->statement("DROP TABLE IF EXISTS iac_state_files");
    }
};
