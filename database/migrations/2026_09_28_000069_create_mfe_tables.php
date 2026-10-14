<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework - Micro-Frontend Tables Migration
 |--------------------------------------------------------------------------
 |
 | Creates persistence tables for registered remote containers, shared scopes,
 | and fragment audit logs.
 |
 */

declare(strict_types=1);

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS mfe_remotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                entry_url TEXT NOT NULL,
                manifest_url TEXT NOT NULL,
                exposes TEXT,
                shared TEXT,
                timeout INTEGER NOT NULL DEFAULT 3000,
                fallback_html TEXT,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS mfe_shared_scopes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                package_name TEXT NOT NULL,
                version TEXT NOT NULL,
                remote_name TEXT NOT NULL,
                singleton INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_mfe_shared_pkg ON mfe_shared_scopes(package_name, version);
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS mfe_shared_scopes;
            DROP TABLE IF EXISTS mfe_remotes;
        ");
    }
};
