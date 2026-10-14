<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Database/Migrations/2026_09_17_000003_create_billing_invoices_table.php
| Module    : Modules\Billing
| Namespace : None (Migration File)
| Purpose   : Creates the billing_invoices database table managing billing receipts and payments.
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
                CREATE TABLE IF NOT EXISTS billing_invoices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NOT NULL,
                    subscription_id INTEGER NULL,
                    invoice_number TEXT NOT NULL UNIQUE,
                    amount REAL NOT NULL,
                    currency TEXT NOT NULL DEFAULT 'USD',
                    status TEXT NOT NULL DEFAULT 'unpaid',
                    due_date TEXT,
                    paid_at TEXT,
                    created_at TEXT,
                    updated_at TEXT
                )
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS billing_invoices (
                    id SERIAL PRIMARY KEY,
                    tenant_id VARCHAR(100) NOT NULL,
                    subscription_id INT NULL,
                    invoice_number VARCHAR(100) NOT NULL UNIQUE,
                    amount NUMERIC(10, 2) NOT NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                    status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
                    due_date TIMESTAMP NULL,
                    paid_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ",
            default => "
                CREATE TABLE IF NOT EXISTS `billing_invoices` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` VARCHAR(100) NOT NULL,
                    `subscription_id` INT UNSIGNED NULL,
                    `invoice_number` VARCHAR(100) NOT NULL UNIQUE,
                    `amount` DECIMAL(10, 2) NOT NULL,
                    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
                    `status` VARCHAR(50) NOT NULL DEFAULT 'unpaid',
                    `due_date` TIMESTAMP NULL,
                    `paid_at` TIMESTAMP NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_inv_tenant` (`tenant_id`),
                    INDEX `idx_inv_status` (`status`)
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
        $db->statement("DROP TABLE IF EXISTS billing_invoices");
    }
};
