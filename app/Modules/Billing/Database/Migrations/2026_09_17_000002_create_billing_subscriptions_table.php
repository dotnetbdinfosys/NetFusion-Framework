<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Database/Migrations/2026_09_17_000002_create_billing_subscriptions_table.php
| Module    : Modules\Billing
| Namespace : None (Migration File)
| Purpose   : Creates the billing_subscriptions table tracking tenant subscription status and lifecycle.
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
                CREATE TABLE IF NOT EXISTS billing_subscriptions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NOT NULL,
                    plan_id INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'active',
                    trial_ends_at TEXT,
                    current_period_start TEXT,
                    current_period_end TEXT,
                    canceled_at TEXT,
                    created_at TEXT,
                    updated_at TEXT
                )
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS billing_subscriptions (
                    id SERIAL PRIMARY KEY,
                    tenant_id VARCHAR(100) NOT NULL,
                    plan_id INT NOT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    trial_ends_at TIMESTAMP NULL,
                    current_period_start TIMESTAMP NULL,
                    current_period_end TIMESTAMP NULL,
                    canceled_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ",
            default => "
                CREATE TABLE IF NOT EXISTS `billing_subscriptions` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` VARCHAR(100) NOT NULL,
                    `plan_id` INT UNSIGNED NOT NULL,
                    `status` VARCHAR(50) NOT NULL DEFAULT 'active',
                    `trial_ends_at` TIMESTAMP NULL,
                    `current_period_start` TIMESTAMP NULL,
                    `current_period_end` TIMESTAMP NULL,
                    `canceled_at` TIMESTAMP NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_tenant_id` (`tenant_id`),
                    INDEX `idx_plan_id` (`plan_id`)
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
        $db->statement("DROP TABLE IF EXISTS billing_subscriptions");
    }
};
