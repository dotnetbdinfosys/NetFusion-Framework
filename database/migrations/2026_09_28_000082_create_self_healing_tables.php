<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000082_create_self_healing_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS healing_incidents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            incident_id VARCHAR(191) NOT NULL UNIQUE,
            service_name VARCHAR(191) NOT NULL,
            metric_name VARCHAR(191) NOT NULL,
            current_value REAL NOT NULL,
            baseline_mean REAL NOT NULL,
            z_score REAL NOT NULL,
            severity VARCHAR(50) NOT NULL DEFAULT 'low',
            status VARCHAR(50) NOT NULL DEFAULT 'detected',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS healing_rca_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id VARCHAR(191) NOT NULL UNIQUE,
            incident_id VARCHAR(191) DEFAULT NULL,
            culprit_service VARCHAR(191) NOT NULL,
            primary_hypothesis VARCHAR(191) NOT NULL,
            confidence REAL NOT NULL,
            recommended_action VARCHAR(100) NOT NULL,
            causal_chain_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS healing_remediation_executions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action_id VARCHAR(191) NOT NULL UNIQUE,
            rca_id VARCHAR(191) DEFAULT NULL,
            target_service VARCHAR(191) NOT NULL,
            action_type VARCHAR(100) NOT NULL,
            canary_percentage REAL NOT NULL DEFAULT 0.0,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            execution_logs_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS healing_remediation_executions");
        $pdo->exec("DROP TABLE IF EXISTS healing_rca_reports");
        $pdo->exec("DROP TABLE IF EXISTS healing_incidents");
    }
};
