<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000018_create_custom_fields_tables.php
| Module    : Forms
| Namespace : N/A (Migration)
| Purpose   : Creates custom_field_definitions and custom_field_values tables.
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

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migration — create custom_field_definitions and custom_field_values tables.
     */
    public function up(Connection $conn): void
    {
        // ------------------------------------------------------------------
        // custom_field_definitions — ফিল্ড স্কিমা ও মেটাডাটা
        // ------------------------------------------------------------------
        $conn->statement("
            CREATE TABLE IF NOT EXISTS custom_field_definitions (
                id             INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                entity_type    TEXT    NOT NULL,
                name           TEXT    NOT NULL,
                label          TEXT    NOT NULL,
                type           TEXT    NOT NULL DEFAULT 'text',
                rules          TEXT    NOT NULL DEFAULT '',
                options        TEXT    NOT NULL DEFAULT '[]',
                default_value  TEXT    NULL,
                is_required    INTEGER NOT NULL DEFAULT 0,
                order_index    INTEGER NOT NULL DEFAULT 0,
                created_at     TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at     TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE UNIQUE INDEX IF NOT EXISTS uq_custom_field_entity_name
                ON custom_field_definitions (entity_type, name)
        ");

        // ------------------------------------------------------------------
        // custom_field_values — সত্ত্বার কাস্টম ফিল্ড ডাটা
        // ------------------------------------------------------------------
        $conn->statement("
            CREATE TABLE IF NOT EXISTS custom_field_values (
                id          INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT    NOT NULL,
                entity_id   INTEGER NOT NULL,
                field_id    INTEGER NOT NULL,
                value       TEXT    NULL,
                created_at  TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at  TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE UNIQUE INDEX IF NOT EXISTS uq_custom_field_val_entity_field
                ON custom_field_values (entity_type, entity_id, field_id)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement('DROP TABLE IF EXISTS custom_field_values');
        $conn->statement('DROP TABLE IF EXISTS custom_field_definitions');
    }
};