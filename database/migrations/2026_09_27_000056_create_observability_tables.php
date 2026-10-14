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
 * Migration: create_observability_tables
 *
 * Tables:
 *   - otel_traces            : Trace root records and span summaries
 *   - otel_spans             : Detailed span event and attribute records
 *   - otel_metrics_snapshots : Aggregated time-series metric snapshots
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS otel_traces (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trace_id VARCHAR(64) UNIQUE,
                service_name VARCHAR(120) NOT NULL,
                duration_ms DECIMAL(10, 3) DEFAULT 0.000,
                spans_count INTEGER DEFAULT 1,
                has_errors BOOLEAN DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS otel_spans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trace_id VARCHAR(64) NOT NULL,
                span_id VARCHAR(32) UNIQUE,
                parent_span_id VARCHAR(32) NULL,
                name VARCHAR(255) NOT NULL,
                kind VARCHAR(32) NOT NULL,
                status_code INTEGER DEFAULT 0,
                status_message TEXT NULL,
                start_time_nano VARCHAR(32) NOT NULL,
                end_time_nano VARCHAR(32) NULL,
                duration_ms DECIMAL(10, 3) DEFAULT 0.000,
                attributes_json TEXT NULL,
                events_json TEXT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS otel_metrics_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                metric_name VARCHAR(160) NOT NULL,
                type VARCHAR(32) NOT NULL,
                unit VARCHAR(32) DEFAULT '1',
                points_count INTEGER DEFAULT 1,
                data_json TEXT NULL,
                created_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS otel_metrics_snapshots");
        $db->statement("DROP TABLE IF EXISTS otel_spans");
        $db->statement("DROP TABLE IF EXISTS otel_traces");
    }
};
