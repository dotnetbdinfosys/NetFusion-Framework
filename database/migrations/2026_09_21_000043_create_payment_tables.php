<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        // 1. Payment Transactions Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_payment_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                intent_id TEXT NOT NULL,
                transaction_id TEXT NOT NULL,
                processor TEXT NOT NULL,
                amount REAL NOT NULL,
                currency TEXT NOT NULL DEFAULT 'USD',
                fee REAL NOT NULL DEFAULT 0.0,
                status TEXT NOT NULL DEFAULT 'succeeded',
                customer_id TEXT,
                metadata TEXT,
                created_at DATETIME NOT NULL
            )
        ");

        // 2. PCI-DSS Tokenization Vault Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_payment_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT UNIQUE NOT NULL,
                customer_id TEXT,
                brand TEXT,
                last4 TEXT,
                exp_month INTEGER,
                exp_year INTEGER,
                masked_pan TEXT,
                encrypted_payload TEXT NOT NULL,
                iv TEXT NOT NULL,
                tag TEXT NOT NULL,
                created_at DATETIME NOT NULL
            )
        ");

        // 3. Split Settlements Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_payment_settlements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                batch_id TEXT,
                vendor_id TEXT NOT NULL,
                amount REAL NOT NULL,
                currency TEXT NOT NULL DEFAULT 'USD',
                transaction_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                due_date DATETIME NOT NULL,
                created_at DATETIME NOT NULL
            )
        ");

        // 4. Double-Entry Accounting Ledger Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_payment_ledger (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_id TEXT NOT NULL,
                account TEXT NOT NULL,
                party_id TEXT NOT NULL DEFAULT 'platform',
                debit REAL NOT NULL DEFAULT 0.0,
                credit REAL NOT NULL DEFAULT 0.0,
                currency TEXT NOT NULL DEFAULT 'USD',
                reference TEXT,
                created_at DATETIME NOT NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS nf_payment_ledger");
        $db->statement("DROP TABLE IF EXISTS nf_payment_settlements");
        $db->statement("DROP TABLE IF EXISTS nf_payment_tokens");
        $db->statement("DROP TABLE IF EXISTS nf_payment_transactions");
    }
};
