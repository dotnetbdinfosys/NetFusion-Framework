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

        // 1. Experiments Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_experiments (
            `key` VARCHAR(128) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            traffic_allocation INTEGER NOT NULL DEFAULT 100,
            winner_variant VARCHAR(64) NULL,
            fallback_variant VARCHAR(64) NULL,
            variants_json TEXT NOT NULL,
            segment_json TEXT NULL,
            goals_json TEXT NULL,
            metadata_json TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 2. Canary Rollouts Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_experiment_canaries (
            flag_key VARCHAR(128) PRIMARY KEY,
            percentage INTEGER NOT NULL DEFAULT 0,
            stages_json TEXT NULL,
            segment_json TEXT NULL,
            error_threshold DOUBLE NOT NULL DEFAULT 5.0,
            kill_switch_triggered INTEGER NOT NULL DEFAULT 0,
            metadata_json TEXT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 3. Segments Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_experiment_segments (
            name VARCHAR(128) PRIMARY KEY,
            match_type VARCHAR(16) NOT NULL DEFAULT 'all',
            description VARCHAR(255) NULL,
            rules_json TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 4. Exposures Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_experiment_exposures (
            id VARCHAR(64) PRIMARY KEY,
            experiment_key VARCHAR(128) NOT NULL,
            variant_name VARCHAR(64) NOT NULL,
            identifier VARCHAR(128) NOT NULL,
            context_json TEXT NULL,
            created_at DOUBLE NOT NULL
        )");

        // 5. Conversions Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_experiment_conversions (
            id VARCHAR(64) PRIMARY KEY,
            experiment_key VARCHAR(128) NOT NULL,
            variant_name VARCHAR(64) NOT NULL,
            identifier VARCHAR(128) NOT NULL,
            goal VARCHAR(128) NOT NULL,
            value DOUBLE NOT NULL DEFAULT 1.0,
            metadata_json TEXT NULL,
            created_at DOUBLE NOT NULL
        )");

        // Indexes for high performance querying
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_exp_exposures ON netfusion_experiment_exposures (experiment_key, variant_name, identifier)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_exp_conversions ON netfusion_experiment_conversions (experiment_key, goal, variant_name)");
        } catch (\Throwable) {
            // Index fallback
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS netfusion_experiment_conversions");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_experiment_exposures");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_experiment_segments");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_experiment_canaries");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_experiments");
    }
};
