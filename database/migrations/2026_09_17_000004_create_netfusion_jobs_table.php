<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000004_create_netfusion_jobs_table.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Database migration creating netfusion_jobs and netfusion_failed_jobs tables.
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
                CREATE TABLE IF NOT EXISTS netfusion_jobs (
                    id TEXT PRIMARY KEY,
                    queue TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    reserved_at INTEGER NULL,
                    available_at INTEGER NOT NULL,
                    created_at INTEGER NOT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_failed_jobs (
                    id TEXT PRIMARY KEY,
                    connection TEXT NOT NULL,
                    queue TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    exception TEXT NOT NULL,
                    failed_at TEXT NOT NULL
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_jobs (
                    id VARCHAR(64) PRIMARY KEY,
                    queue VARCHAR(100) NOT NULL,
                    payload TEXT NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    reserved_at BIGINT NULL,
                    available_at BIGINT NOT NULL,
                    created_at BIGINT NOT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS netfusion_failed_jobs (
                    id VARCHAR(64) PRIMARY KEY,
                    connection VARCHAR(100) NOT NULL,
                    queue VARCHAR(100) NOT NULL,
                    payload TEXT NOT NULL,
                    exception TEXT NOT NULL,
                    failed_at TIMESTAMP NOT NULL
                )
            ");
        } else {
            // MySQL / MariaDB
            $db->statement("
                CREATE TABLE IF NOT EXISTS `netfusion_jobs` (
                    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `queue` VARCHAR(100) NOT NULL,
                    `payload` LONGTEXT NOT NULL,
                    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    `reserved_at` BIGINT UNSIGNED NULL,
                    `available_at` BIGINT UNSIGNED NOT NULL,
                    `created_at` BIGINT UNSIGNED NOT NULL,
                    INDEX `idx_jobs_queue_reserved` (`queue`, `reserved_at`),
                    INDEX `idx_jobs_available` (`available_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS `netfusion_failed_jobs` (
                    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `connection` VARCHAR(100) NOT NULL,
                    `queue` VARCHAR(100) NOT NULL,
                    `payload` LONGTEXT NOT NULL,
                    `exception` LONGTEXT NOT NULL,
                    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_failed_queue` (`queue`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS netfusion_failed_jobs");
        $db->statement("DROP TABLE IF EXISTS netfusion_jobs");
    }
};
