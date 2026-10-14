<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework - OLAP Analytical Segments Migration
 |--------------------------------------------------------------------------
 |
 | Creates persistence tables for historical analytical segments, data sources,
 | and query execution logs.
 |
 */

declare(strict_types=1);

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS olap_datasources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                dimensions TEXT,
                metrics TEXT,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS olap_segments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                segment_id TEXT NOT NULL UNIQUE,
                datasource TEXT NOT NULL,
                start_time INTEGER NOT NULL,
                end_time INTEGER NOT NULL,
                row_count INTEGER NOT NULL DEFAULT 0,
                segment_payload TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_olap_segments_lookup ON olap_segments(datasource, start_time, end_time);
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS olap_segments;
            DROP TABLE IF EXISTS olap_datasources;
        ");
    }
};
