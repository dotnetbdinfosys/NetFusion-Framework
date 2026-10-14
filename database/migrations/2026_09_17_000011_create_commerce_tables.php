<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000011_create_commerce_tables.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Migration creating customers, orders, order items, and POS payments.
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
                CREATE TABLE IF NOT EXISTS pos_customers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NULL,
                    name TEXT NOT NULL,
                    email TEXT NULL,
                    phone TEXT NULL,
                    address TEXT NULL,
                    loyalty_points INTEGER NOT NULL DEFAULT 0,
                    balance REAL NOT NULL DEFAULT 0.0,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_orders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NULL,
                    order_number TEXT NOT NULL UNIQUE,
                    customer_id INTEGER NULL,
                    subtotal REAL NOT NULL DEFAULT 0.0,
                    discount_amount REAL NOT NULL DEFAULT 0.0,
                    tax_amount REAL NOT NULL DEFAULT 0.0,
                    total_amount REAL NOT NULL DEFAULT 0.0,
                    paid_amount REAL NOT NULL DEFAULT 0.0,
                    payment_status TEXT NOT NULL DEFAULT 'unpaid',
                    order_status TEXT NOT NULL DEFAULT 'pending',
                    type TEXT NOT NULL DEFAULT 'pos',
                    warehouse_id INTEGER NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_order_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    product_id INTEGER NULL,
                    product_name TEXT NOT NULL,
                    quantity REAL NOT NULL DEFAULT 1.0,
                    unit_price REAL NOT NULL DEFAULT 0.0,
                    subtotal REAL NOT NULL DEFAULT 0.0,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    amount REAL NOT NULL DEFAULT 0.0,
                    payment_method TEXT NOT NULL DEFAULT 'cash',
                    transaction_reference TEXT NULL,
                    paid_at TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");
        } else {
            // MySQL & PostgreSQL
            $autoInc = $driver === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_customers (
                    id {$autoInc},
                    tenant_id VARCHAR(64) NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NULL,
                    phone VARCHAR(50) NULL,
                    address TEXT NULL,
                    loyalty_points INT NOT NULL DEFAULT 0,
                    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(30) NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_pos_customers_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_orders (
                    id {$autoInc},
                    tenant_id VARCHAR(64) NULL,
                    order_number VARCHAR(64) NOT NULL UNIQUE,
                    customer_id BIGINT UNSIGNED NULL,
                    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
                    order_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                    type VARCHAR(20) NOT NULL DEFAULT 'pos',
                    warehouse_id BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_pos_orders_tenant (tenant_id),
                    INDEX idx_pos_orders_cust (customer_id),
                    INDEX idx_pos_orders_status (order_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_order_items (
                    id {$autoInc},
                    order_id BIGINT UNSIGNED NOT NULL,
                    product_id BIGINT UNSIGNED NULL,
                    product_name VARCHAR(255) NOT NULL,
                    quantity DECIMAL(14,2) NOT NULL DEFAULT 1.00,
                    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_pos_items_order (order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS pos_payments (
                    id {$autoInc},
                    order_id BIGINT UNSIGNED NOT NULL,
                    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
                    transaction_reference VARCHAR(100) NULL,
                    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_pos_payments_order (order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS pos_payments");
        $db->statement("DROP TABLE IF EXISTS pos_order_items");
        $db->statement("DROP TABLE IF EXISTS pos_orders");
        $db->statement("DROP TABLE IF EXISTS pos_customers");
    }
};
