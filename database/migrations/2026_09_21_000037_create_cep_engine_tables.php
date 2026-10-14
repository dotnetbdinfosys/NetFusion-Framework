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

        // 1. CEP Rules Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_cep_rules (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(128) UNIQUE NOT NULL,
            stream VARCHAR(128) NOT NULL,
            description TEXT,
            enabled INTEGER NOT NULL DEFAULT 1,
            definition_json TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 2. CEP Alerts & Pattern Matches
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_cep_alerts (
            id VARCHAR(64) PRIMARY KEY,
            rule_name VARCHAR(128) NOT NULL,
            stream VARCHAR(128) NOT NULL,
            partition_key VARCHAR(255) NOT NULL DEFAULT 'global',
            severity VARCHAR(32) NOT NULL DEFAULT 'INFO',
            payload TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )");

        // 3. CEP State Snapshots (for window & NFA persistence)
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_cep_state_snapshots (
            rule_name VARCHAR(128) NOT NULL,
            partition_key VARCHAR(255) NOT NULL DEFAULT 'global',
            state_data TEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (rule_name, partition_key)
        )");

        // 4. CEP Runtime Metrics
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_cep_metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            metric_name VARCHAR(128) NOT NULL,
            metric_value DOUBLE NOT NULL,
            tags TEXT,
            recorded_at DATETIME NOT NULL
        )");

        // Indices
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cep_alerts_rule ON nf_cep_alerts(rule_name)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cep_alerts_created ON nf_cep_alerts(created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cep_metrics_name ON nf_cep_metrics(metric_name, recorded_at)");
        } catch (\Throwable) {
            // Indices already exist or not supported
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS nf_cep_metrics");
        $pdo->exec("DROP TABLE IF EXISTS nf_cep_state_snapshots");
        $pdo->exec("DROP TABLE IF EXISTS nf_cep_alerts");
        $pdo->exec("DROP TABLE IF EXISTS nf_cep_rules");
    }
};
