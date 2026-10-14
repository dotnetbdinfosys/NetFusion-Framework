<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000015_create_personal_access_tokens_table.php
| Module    : Auth
| Namespace : None (Database Migration)
| Purpose   : Database migration creating personal access tokens table for API authentication.
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
                CREATE TABLE IF NOT EXISTS personal_access_tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tokenable_type TEXT NOT NULL,
                    tokenable_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    token TEXT NOT NULL,
                    abilities TEXT NULL,
                    last_used_at TEXT NULL,
                    expires_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            ");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_pat_tokenable ON personal_access_tokens(tokenable_type, tokenable_id)");
            $db->statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_pat_token ON personal_access_tokens(token)");
        } else {
            $db->statement("
                CREATE TABLE IF NOT EXISTS personal_access_tokens (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tokenable_type VARCHAR(191) NOT NULL,
                    tokenable_id BIGINT UNSIGNED NOT NULL,
                    name VARCHAR(191) NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    abilities JSON NULL,
                    last_used_at DATETIME NULL,
                    expires_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX idx_pat_tokenable (tokenable_type, tokenable_id),
                    UNIQUE KEY uq_pat_token (token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS personal_access_tokens");
    }
};
