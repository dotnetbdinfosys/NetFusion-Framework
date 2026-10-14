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
 * Migration: create_schema_registry_tables
 *
 * Tables:
 *   - schema_registry_subjects : Registered subjects and compatibility policy
 *   - schema_registry_versions : Specific version definitions and fingerprints
 *   - schema_registry_events   : Published CloudEvents journal
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS schema_registry_subjects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subject VARCHAR(160) UNIQUE NOT NULL,
                compatibility VARCHAR(32) DEFAULT 'BACKWARD',
                latest_version INTEGER DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS schema_registry_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                schema_id INTEGER UNIQUE NOT NULL,
                subject VARCHAR(160) NOT NULL,
                version INTEGER NOT NULL,
                schema_type VARCHAR(32) NOT NULL,
                schema_definition TEXT NOT NULL,
                fingerprint VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS schema_registry_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id VARCHAR(64) UNIQUE NOT NULL,
                type VARCHAR(160) NOT NULL,
                source VARCHAR(255) NOT NULL,
                subject VARCHAR(160) NULL,
                datacontenttype VARCHAR(64) NULL,
                dataschema VARCHAR(255) NULL,
                time VARCHAR(32) NULL,
                payload_json TEXT NULL,
                created_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS schema_registry_events");
        $db->statement("DROP TABLE IF EXISTS schema_registry_versions");
        $db->statement("DROP TABLE IF EXISTS schema_registry_subjects");
    }
};
