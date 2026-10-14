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

        // 1. WAF Custom & Virtual Patch Rules Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_waf_rules (
                id VARCHAR(128) PRIMARY KEY,
                description TEXT NULL,
                field VARCHAR(64) NOT NULL,
                operator VARCHAR(32) NOT NULL,
                pattern TEXT NOT NULL,
                action VARCHAR(32) DEFAULT 'BLOCK',
                score REAL DEFAULT 5.0,
                enabled INT DEFAULT 1,
                created_at INT NOT NULL
            )
        ");

        // 2. WAF IP Blocks & Quarantine Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_waf_ip_blocks (
                ip VARCHAR(64) PRIMARY KEY,
                reason TEXT NOT NULL,
                expires_at INT NOT NULL,
                created_at INT NOT NULL
            )
        ");

        // 3. WAF Security Threat Events Table
        $autoInc = ($driver === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_waf_threat_events (
                id {$autoInc},
                ip VARCHAR(64) NOT NULL,
                method VARCHAR(16) NOT NULL,
                uri TEXT NOT NULL,
                action VARCHAR(32) NOT NULL,
                reason TEXT NULL,
                rule_id VARCHAR(64) NULL,
                anomaly_score REAL DEFAULT 0.0,
                bot_type VARCHAR(64) NULL,
                ja3_hash VARCHAR(64) NULL,
                payload_meta TEXT NULL,
                created_at INT NOT NULL
            )
        ");

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_nf_waf_events_ip ON nf_waf_threat_events(ip)
        ");

        // 4. WAF Aggregate Metrics Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_waf_metrics (
                metric_name VARCHAR(64) PRIMARY KEY,
                metric_count INT DEFAULT 0,
                updated_at INT NOT NULL
            )
        ");
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS nf_waf_metrics");
        $pdo->exec("DROP TABLE IF EXISTS nf_waf_threat_events");
        $pdo->exec("DROP TABLE IF EXISTS nf_waf_ip_blocks");
        $pdo->exec("DROP TABLE IF EXISTS nf_waf_rules");
    }
};
