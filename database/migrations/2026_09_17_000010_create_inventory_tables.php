<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000010_create_inventory_tables.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Migration creating categories, warehouses, products, stock levels, and movements.
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
                CREATE TABLE IF NOT EXISTS inv_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    code TEXT NOT NULL UNIQUE,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_warehouses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    location TEXT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    category_id INTEGER NULL,
                    sku TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    unit TEXT NOT NULL DEFAULT 'pcs',
                    cost_price REAL NOT NULL DEFAULT 0.00,
                    selling_price REAL NOT NULL DEFAULT 0.00,
                    min_stock_alert REAL NOT NULL DEFAULT 10.00,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_warehouse_stocks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    warehouse_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    quantity REAL NOT NULL DEFAULT 0.00,
                    reserved_quantity REAL NOT NULL DEFAULT 0.00,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_stock_movements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    movement_number TEXT NOT NULL UNIQUE,
                    product_id INTEGER NOT NULL,
                    from_warehouse_id INTEGER NULL,
                    to_warehouse_id INTEGER NULL,
                    type TEXT NOT NULL,
                    quantity REAL NOT NULL,
                    unit_cost REAL NOT NULL DEFAULT 0.00,
                    reference TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_categories (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    code VARCHAR(32) NOT NULL UNIQUE,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_warehouses (
                    id SERIAL PRIMARY KEY,
                    code VARCHAR(32) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    location VARCHAR(255) NULL,
                    is_active SMALLINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_products (
                    id SERIAL PRIMARY KEY,
                    category_id INTEGER NULL,
                    sku VARCHAR(64) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    unit VARCHAR(32) NOT NULL DEFAULT 'pcs',
                    cost_price NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    selling_price NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    min_stock_alert NUMERIC(15,2) NOT NULL DEFAULT 10.00,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_warehouse_stocks (
                    id SERIAL PRIMARY KEY,
                    warehouse_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    quantity NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    reserved_quantity NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_stock_movements (
                    id SERIAL PRIMARY KEY,
                    movement_number VARCHAR(64) NOT NULL UNIQUE,
                    product_id INTEGER NOT NULL,
                    from_warehouse_id INTEGER NULL,
                    to_warehouse_id INTEGER NULL,
                    type VARCHAR(16) NOT NULL,
                    quantity NUMERIC(15,2) NOT NULL,
                    unit_cost NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    reference VARCHAR(128) NULL,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");
        } else {
            // MySQL
            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_categories (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    code VARCHAR(32) NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_cat_code (code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_warehouses (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(32) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    location VARCHAR(255) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_wh_code (code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_products (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    category_id BIGINT UNSIGNED NULL,
                    sku VARCHAR(64) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    unit VARCHAR(32) NOT NULL DEFAULT 'pcs',
                    cost_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    selling_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    min_stock_alert DECIMAL(15,2) NOT NULL DEFAULT 10.00,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_prod_sku (sku),
                    INDEX idx_prod_cat (category_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_warehouse_stocks (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    warehouse_id BIGINT UNSIGNED NOT NULL,
                    product_id BIGINT UNSIGNED NOT NULL,
                    quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    reserved_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_wh_product (warehouse_id, product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS inv_stock_movements (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    movement_number VARCHAR(64) NOT NULL,
                    product_id BIGINT UNSIGNED NOT NULL,
                    from_warehouse_id BIGINT UNSIGNED NULL,
                    to_warehouse_id BIGINT UNSIGNED NULL,
                    type VARCHAR(16) NOT NULL,
                    quantity DECIMAL(15,2) NOT NULL,
                    unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    reference VARCHAR(128) NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_move_num (movement_number),
                    INDEX idx_move_prod (product_id),
                    INDEX idx_move_type (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS inv_stock_movements");
        $db->statement("DROP TABLE IF EXISTS inv_warehouse_stocks");
        $db->statement("DROP TABLE IF EXISTS inv_products");
        $db->statement("DROP TABLE IF EXISTS inv_warehouses");
        $db->statement("DROP TABLE IF EXISTS inv_categories");
    }
};
