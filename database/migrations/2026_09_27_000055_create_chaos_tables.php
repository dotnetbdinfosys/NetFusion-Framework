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
 * Migration: create_chaos_tables
 *
 * Tables:
 *   - chaos_experiments     : Catalog of registered GameDay experiments
 *   - chaos_execution_logs  : Telemetry, blast-radius audits & resiliency scores
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS chaos_experiments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                experiment_id VARCHAR(64) UNIQUE,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                tags_json TEXT NULL,
                is_active BOOLEAN DEFAULT 1,
                duration_seconds INTEGER DEFAULT 60,
                config_json TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS chaos_execution_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                execution_id VARCHAR(64) UNIQUE,
                experiment_id VARCHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL,
                resiliency_score DECIMAL(5, 2) DEFAULT 0.00,
                steady_state_pre BOOLEAN DEFAULT 0,
                steady_state_post BOOLEAN DEFAULT 0,
                violations_count INTEGER DEFAULT 0,
                duration_ms DECIMAL(10, 2) DEFAULT 0.00,
                abort_reason TEXT NULL,
                telemetry_json TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS chaos_execution_logs");
        $db->statement("DROP TABLE IF EXISTS chaos_experiments");
    }
};
