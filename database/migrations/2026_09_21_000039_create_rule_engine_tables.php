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

use NetFusion\Core\Database\Connection;

return new class {
    public function up(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // 1. Rules Definition Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_rules (
                name VARCHAR(128) PRIMARY KEY,
                description TEXT NULL,
                salience INT DEFAULT 0,
                patterns_json TEXT NOT NULL,
                enabled INT DEFAULT 1,
                metadata_json TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 2. DMN Decision Tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_decision_tables (
                name VARCHAR(128) PRIMARY KEY,
                hit_policy VARCHAR(32) NOT NULL DEFAULT 'FIRST',
                inputs_json TEXT NOT NULL,
                outputs_json TEXT NOT NULL,
                rules_json TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 3. Rule & Decision Audit Trail Logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_rule_audit_logs (
                id VARCHAR(64) PRIMARY KEY,
                rule_name VARCHAR(128) NULL,
                decision_table VARCHAR(128) NULL,
                facts_snapshot_json TEXT NULL,
                result_json TEXT NULL,
                execution_time_ms REAL DEFAULT 0.0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 4. Rule Engine Performance Metrics
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_rule_metrics (
                id " . ($driver === 'sqlite' ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "BIGINT AUTO_INCREMENT PRIMARY KEY") . ",
                metric_name VARCHAR(64) NOT NULL,
                metric_value REAL NOT NULL,
                tags_json TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS nf_rule_metrics");
        $pdo->exec("DROP TABLE IF EXISTS nf_rule_audit_logs");
        $pdo->exec("DROP TABLE IF EXISTS nf_decision_tables");
        $pdo->exec("DROP TABLE IF EXISTS nf_rules");
    }
};
