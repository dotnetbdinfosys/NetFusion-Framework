<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000007_create_hrm_tables.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Creates hrm_departments, hrm_employees, and hrm_payrolls tables.
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
                CREATE TABLE IF NOT EXISTS hrm_departments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NOT NULL,
                    name TEXT NOT NULL,
                    code TEXT NOT NULL,
                    budget REAL NOT NULL DEFAULT 0.00,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS hrm_employees (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NOT NULL,
                    department_id INTEGER NOT NULL,
                    employee_code TEXT NOT NULL,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT NULL,
                    designation TEXT NOT NULL,
                    basic_salary REAL NOT NULL DEFAULT 0.00,
                    joined_date TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS hrm_payrolls (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NOT NULL,
                    employee_id INTEGER NOT NULL,
                    month INTEGER NOT NULL,
                    year INTEGER NOT NULL,
                    basic_salary REAL NOT NULL,
                    allowances REAL NOT NULL DEFAULT 0.00,
                    deductions REAL NOT NULL DEFAULT 0.00,
                    net_salary REAL NOT NULL,
                    status TEXT NOT NULL DEFAULT 'draft',
                    paid_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            ");
        } else {
            // MySQL / MariaDB / PostgreSQL
            $db->statement("
                CREATE TABLE IF NOT EXISTS hrm_departments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id VARCHAR(50) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    code VARCHAR(20) NOT NULL,
                    budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS hrm_employees (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id VARCHAR(50) NOT NULL,
                    department_id INT NOT NULL,
                    employee_code VARCHAR(50) NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    phone VARCHAR(30) NULL,
                    designation VARCHAR(100) NOT NULL,
                    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    joined_date DATE NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS hrm_payrolls (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id VARCHAR(50) NOT NULL,
                    employee_id INT NOT NULL,
                    month INT NOT NULL,
                    year INT NOT NULL,
                    basic_salary DECIMAL(12,2) NOT NULL,
                    allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    net_salary DECIMAL(12,2) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    paid_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS hrm_payrolls");
        $db->statement("DROP TABLE IF EXISTS hrm_employees");
        $db->statement("DROP TABLE IF EXISTS hrm_departments");
    }
};
