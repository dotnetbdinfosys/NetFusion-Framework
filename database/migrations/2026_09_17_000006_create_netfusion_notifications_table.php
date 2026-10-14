<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000006_create_netfusion_notifications_table.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Database migration creating netfusion_notifications table.
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
                CREATE TABLE IF NOT EXISTS netfusion_notifications (
                    id TEXT PRIMARY KEY,
                    type TEXT NOT NULL,
                    notifiable_type TEXT NOT NULL,
                    notifiable_id TEXT NOT NULL,
                    data TEXT NOT NULL,
                    read_at TEXT NULL,
                    created_at TEXT NOT NULL
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_notifications (
                    id VARCHAR(36) PRIMARY KEY,
                    type VARCHAR(255) NOT NULL,
                    notifiable_type VARCHAR(255) NOT NULL,
                    notifiable_id VARCHAR(255) NOT NULL,
                    data TEXT NOT NULL,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL
                )
            ");
        } else {
            // MySQL / MariaDB
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_notifications (
                    id VARCHAR(36) PRIMARY KEY,
                    type VARCHAR(255) NOT NULL,
                    notifiable_type VARCHAR(255) NOT NULL,
                    notifiable_id VARCHAR(255) NOT NULL,
                    data LONGTEXT NOT NULL,
                    read_at DATETIME NULL,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS netfusion_notifications");
    }
};
