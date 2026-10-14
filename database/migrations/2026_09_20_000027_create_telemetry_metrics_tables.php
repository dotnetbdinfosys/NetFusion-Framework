<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework Migration
|--------------------------------------------------------------------------
|
| File      : 2026_09_20_000027_create_telemetry_metrics_tables.php
| Module    : Telemetry & Metrics
| Purpose   : Schema for persistent metric snapshots and telemetry aggregations.
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

return new class {
    /**
     * Run the migration.
     */
    public function up(mixed $conn): void
    {
        $exec = function(string $sql) use ($conn) {
            if ($conn instanceof \PDO) {
                $conn->exec($sql);
            } else {
                $conn->statement($sql);
            }
        };

        // 1. Metric Aggregations / Snapshots Table
        $exec("
            CREATE TABLE IF NOT EXISTS netfusion_telemetry_metrics (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                metric_name VARCHAR(191) NOT NULL,
                type        VARCHAR(50)  NOT NULL,
                value       REAL         NOT NULL DEFAULT 0.0,
                labels      TEXT         NULL,
                timestamp   REAL         NOT NULL,
                created_at  TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $exec("
            CREATE INDEX IF NOT EXISTS idx_telemetry_name_ts
                ON netfusion_telemetry_metrics (metric_name, timestamp DESC)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(mixed $conn): void
    {
        $exec = function(string $sql) use ($conn) {
            if ($conn instanceof \PDO) {
                $conn->exec($sql);
            } else {
                $conn->statement($sql);
            }
        };

        $exec("DROP TABLE IF EXISTS netfusion_telemetry_metrics");
    }
};
