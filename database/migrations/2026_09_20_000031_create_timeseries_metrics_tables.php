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

        // 1. Raw Time-Series Ingestion Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_timeseries_raw (
            id VARCHAR(64) PRIMARY KEY,
            metric VARCHAR(128) NOT NULL,
            timestamp DOUBLE NOT NULL,
            value DOUBLE NOT NULL,
            tags_json TEXT NULL,
            created_at DATETIME NOT NULL
        )");

        // 2. Rollup Downsampled Aggregations Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_timeseries_rollups (
            id VARCHAR(64) PRIMARY KEY,
            metric VARCHAR(128) NOT NULL,
            bucket_timestamp DOUBLE NOT NULL,
            window_size INTEGER NOT NULL,
            avg_val DOUBLE NOT NULL,
            min_val DOUBLE NOT NULL,
            max_val DOUBLE NOT NULL,
            sum_val DOUBLE NOT NULL,
            count_val INTEGER NOT NULL,
            tags_json TEXT NULL
        )");

        // Indexes for fast timestamp queries
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ts_raw_metric_ts ON netfusion_timeseries_raw (metric, timestamp)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ts_rollup_metric_ts ON netfusion_timeseries_rollups (metric, bucket_timestamp)");
        } catch (\Throwable) {
            // Index creation fallback
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS netfusion_timeseries_rollups");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_timeseries_raw");
    }
};
