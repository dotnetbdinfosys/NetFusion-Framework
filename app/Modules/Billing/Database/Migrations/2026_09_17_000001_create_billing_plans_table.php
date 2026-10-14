<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Database/Migrations/2026_09_17_000001_create_billing_plans_table.php
| Module    : Modules\Billing
| Namespace : None (Migration File)
| Purpose   : Creates the billing_plans database table storing SaaS subscription tiers.
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

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(Connection $db): void
    {
        $driver = $db->getDriverName();

        $sql = match ($driver) {
            'sqlite' => "
                CREATE TABLE IF NOT EXISTS billing_plans (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    price REAL NOT NULL,
                    currency TEXT NOT NULL DEFAULT 'USD',
                    billing_interval TEXT NOT NULL DEFAULT 'monthly',
                    features TEXT,
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT,
                    updated_at TEXT
                )
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS billing_plans (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(100) NOT NULL UNIQUE,
                    price NUMERIC(10, 2) NOT NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                    billing_interval VARCHAR(50) NOT NULL DEFAULT 'monthly',
                    features TEXT,
                    is_active SMALLINT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ",
            default => "
                CREATE TABLE IF NOT EXISTS `billing_plans` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(100) NOT NULL UNIQUE,
                    `price` DECIMAL(10, 2) NOT NULL,
                    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
                    `billing_interval` VARCHAR(50) NOT NULL DEFAULT 'monthly',
                    `features` TEXT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
        };

        $db->statement($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS billing_plans");
    }
};
