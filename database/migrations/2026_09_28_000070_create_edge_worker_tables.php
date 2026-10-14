<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Edge Computing & Serverless Worker Migration
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

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        // 1. Registered Edge Workers
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS edge_workers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(120) NOT NULL UNIQUE,
                type VARCHAR(20) NOT NULL DEFAULT 'php',
                memory_limit_mb INTEGER NOT NULL DEFAULT 128,
                cpu_timeout_ms INTEGER NOT NULL DEFAULT 50,
                version INTEGER NOT NULL DEFAULT 1,
                env TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Edge Routing Rules
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS edge_routes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pattern VARCHAR(255) NOT NULL,
                worker_name VARCHAR(120) NOT NULL,
                methods VARCHAR(100) NOT NULL DEFAULT 'ALL',
                weight INTEGER NOT NULL DEFAULT 100,
                country VARCHAR(10) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Edge Worker Invocation Telemetry Logs
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS edge_worker_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                worker_name VARCHAR(120) NOT NULL,
                method VARCHAR(10) NOT NULL,
                url TEXT NOT NULL,
                status INTEGER NOT NULL,
                duration_ms REAL NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("DROP TABLE IF EXISTS edge_worker_logs;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS edge_routes;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS edge_workers;");
    }
};
