<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000016_create_feature_flags_table.php
| Module    : Features
| Namespace : None (Database Migration)
| Purpose   : Database migration creating feature_flags table for dynamic toggles.
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
use NetFusion\Core\Database\Migrations\Migration;

return new class extends Migration {
    public function up(Connection $db): void
    {
        $driver = $db->getDriverName();

        if ($driver === 'sqlite') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS feature_flags (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    status TEXT NOT NULL DEFAULT 'active',
                    percentage INTEGER NULL,
                    rules TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            ");
            $db->statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_feature_flags_name ON feature_flags(name)");
        } else {
            $db->statement("
                CREATE TABLE IF NOT EXISTS feature_flags (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(191) NOT NULL UNIQUE,
                    status VARCHAR(32) NOT NULL DEFAULT 'active',
                    percentage INT UNSIGNED NULL,
                    rules JSON NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_feature_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS feature_flags");
    }
};
