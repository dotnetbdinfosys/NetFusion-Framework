<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000009_create_accounting_tables.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Database migration for double-entry Chart of Accounts, Journal Entries, and Items.
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
                CREATE TABLE IF NOT EXISTS acc_chart_of_accounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    type TEXT NOT NULL,
                    normal_balance TEXT NOT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_journal_entries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_number TEXT NOT NULL UNIQUE,
                    entry_date TEXT NOT NULL,
                    reference TEXT NULL,
                    description TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'draft',
                    total_amount REAL NOT NULL DEFAULT 0.00,
                    posted_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_journal_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    journal_entry_id INTEGER NOT NULL,
                    account_id INTEGER NOT NULL,
                    debit REAL NOT NULL DEFAULT 0.00,
                    credit REAL NOT NULL DEFAULT 0.00,
                    description TEXT NULL,
                    created_at TEXT NULL,
                    updated_at TEXT NULL
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_chart_of_accounts (
                    id SERIAL PRIMARY KEY,
                    code VARCHAR(32) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    type VARCHAR(32) NOT NULL,
                    normal_balance VARCHAR(8) NOT NULL,
                    is_active SMALLINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_journal_entries (
                    id SERIAL PRIMARY KEY,
                    entry_number VARCHAR(64) NOT NULL UNIQUE,
                    entry_date DATE NOT NULL,
                    reference VARCHAR(128) NULL,
                    description TEXT NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'draft',
                    total_amount NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    posted_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_journal_items (
                    id SERIAL PRIMARY KEY,
                    journal_entry_id INTEGER NOT NULL,
                    account_id INTEGER NOT NULL,
                    debit NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    credit NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    description TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ");
        } else {
            // MySQL
            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_chart_of_accounts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(32) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    type VARCHAR(32) NOT NULL,
                    normal_balance VARCHAR(8) NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_account_code (code),
                    INDEX idx_account_type (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_journal_entries (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    entry_number VARCHAR(64) NOT NULL,
                    entry_date DATE NOT NULL,
                    reference VARCHAR(128) NULL,
                    description TEXT NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'draft',
                    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    posted_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_entry_number (entry_number),
                    INDEX idx_entry_status (status),
                    INDEX idx_entry_date (entry_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS acc_journal_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    journal_entry_id BIGINT UNSIGNED NOT NULL,
                    account_id BIGINT UNSIGNED NOT NULL,
                    debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    description TEXT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    INDEX idx_item_entry (journal_entry_id),
                    INDEX idx_item_account (account_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS acc_journal_items");
        $db->statement("DROP TABLE IF EXISTS acc_journal_entries");
        $db->statement("DROP TABLE IF EXISTS acc_chart_of_accounts");
    }
};
