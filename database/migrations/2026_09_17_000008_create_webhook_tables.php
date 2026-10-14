<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000008_create_webhook_tables.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Database migration creating webhook subscriptions and audit delivery tables.
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
                CREATE TABLE IF NOT EXISTS netfusion_webhook_subscriptions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    topic TEXT NOT NULL,
                    target_url TEXT NOT NULL,
                    secret TEXT NOT NULL,
                    retry_limit INTEGER NOT NULL DEFAULT 3,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_webhook_deliveries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subscription_id INTEGER NOT NULL,
                    delivery_id TEXT NOT NULL,
                    event TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    status_code INTEGER NOT NULL,
                    response_body TEXT NULL,
                    error_message TEXT NULL,
                    duration_ms REAL NOT NULL,
                    attempt INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_webhook_subscriptions (
                    id SERIAL PRIMARY KEY,
                    topic VARCHAR(255) NOT NULL,
                    target_url TEXT NOT NULL,
                    secret VARCHAR(64) NOT NULL,
                    retry_limit INTEGER NOT NULL DEFAULT 3,
                    is_active SMALLINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_webhook_deliveries (
                    id SERIAL PRIMARY KEY,
                    subscription_id INTEGER NOT NULL,
                    delivery_id VARCHAR(64) NOT NULL,
                    event VARCHAR(255) NOT NULL,
                    payload TEXT NOT NULL,
                    status_code INTEGER NOT NULL,
                    response_body TEXT NULL,
                    error_message TEXT NULL,
                    duration_ms NUMERIC(10,2) NOT NULL,
                    attempt INTEGER NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL
                )
            ");
        } else {
            // MySQL / MariaDB
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_webhook_subscriptions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    topic VARCHAR(255) NOT NULL,
                    target_url TEXT NOT NULL,
                    secret VARCHAR(64) NOT NULL,
                    retry_limit INT NOT NULL DEFAULT 3,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    INDEX idx_webhook_sub_topic (topic(191)),
                    INDEX idx_webhook_sub_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_webhook_deliveries (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    subscription_id BIGINT UNSIGNED NOT NULL,
                    delivery_id VARCHAR(64) NOT NULL,
                    event VARCHAR(255) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    status_code INT NOT NULL,
                    response_body MEDIUMTEXT NULL,
                    error_message TEXT NULL,
                    duration_ms DECIMAL(8,2) NOT NULL,
                    attempt INT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    INDEX idx_webhook_del_event (event(191)),
                    INDEX idx_webhook_del_status (status_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS netfusion_webhook_deliveries");
        $db->statement("DROP TABLE IF EXISTS netfusion_webhook_subscriptions");
    }
};
