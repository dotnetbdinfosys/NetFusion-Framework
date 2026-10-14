<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000005_create_netfusion_cache_table.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Database migration creating netfusion_cache table.
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
    /**
     * Run the migrations.
     */
    public function up(Connection $db): void
    {
        $driver = $db->getDriverName();

        if ($driver === 'sqlite') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_cache (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL,
                    expiration INTEGER NOT NULL
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_cache (
                    \"key\" VARCHAR(255) PRIMARY KEY,
                    \"value\" TEXT NOT NULL,
                    \"expiration\" BIGINT NOT NULL
                )
            ");
        } else {
            // MySQL / MariaDB
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_cache (
                    `key` VARCHAR(255) PRIMARY KEY,
                    `value` LONGTEXT NOT NULL,
                    `expiration` BIGINT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS netfusion_cache");
    }
};
