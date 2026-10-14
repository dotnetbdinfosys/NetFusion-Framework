<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000012_create_crm_tables.php
| Module    : Database
| Namespace : None (Database Migration)
| Purpose   : Migration creating CRM leads, sales deals, support tickets and replies.
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
                CREATE TABLE IF NOT EXISTS crm_leads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NULL,
                    name TEXT NOT NULL,
                    company TEXT NULL,
                    email TEXT NULL,
                    phone TEXT NULL,
                    status TEXT NOT NULL DEFAULT 'new',
                    source TEXT NULL DEFAULT 'website',
                    assigned_to INTEGER NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_deals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NULL,
                    lead_id INTEGER NULL,
                    title TEXT NOT NULL,
                    amount REAL NOT NULL DEFAULT 0.0,
                    stage TEXT NOT NULL DEFAULT 'prospect',
                    expected_close_date TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_tickets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id TEXT NULL,
                    ticket_number TEXT NOT NULL UNIQUE,
                    customer_name TEXT NOT NULL,
                    customer_email TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    description TEXT NOT NULL,
                    priority TEXT NOT NULL DEFAULT 'medium',
                    status TEXT NOT NULL DEFAULT 'open',
                    assigned_to INTEGER NULL,
                    sla_due_at TEXT NULL,
                    resolved_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_ticket_replies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ticket_id INTEGER NOT NULL,
                    user_id INTEGER NULL,
                    sender_type TEXT NOT NULL DEFAULT 'agent',
                    message TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NULL
                )
            ");
        } else {
            $autoInc = $driver === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_leads (
                    id {$autoInc},
                    tenant_id VARCHAR(64) NULL,
                    name VARCHAR(255) NOT NULL,
                    company VARCHAR(255) NULL,
                    email VARCHAR(255) NULL,
                    phone VARCHAR(50) NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'new',
                    source VARCHAR(50) NULL DEFAULT 'website',
                    assigned_to BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_crm_leads_tenant (tenant_id),
                    INDEX idx_crm_leads_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_deals (
                    id {$autoInc},
                    tenant_id VARCHAR(64) NULL,
                    lead_id BIGINT UNSIGNED NULL,
                    title VARCHAR(255) NOT NULL,
                    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    stage VARCHAR(30) NOT NULL DEFAULT 'prospect',
                    expected_close_date DATE NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_crm_deals_tenant (tenant_id),
                    INDEX idx_crm_deals_lead (lead_id),
                    INDEX idx_crm_deals_stage (stage)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_tickets (
                    id {$autoInc},
                    tenant_id VARCHAR(64) NULL,
                    ticket_number VARCHAR(64) NOT NULL UNIQUE,
                    customer_name VARCHAR(255) NOT NULL,
                    customer_email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
                    status VARCHAR(30) NOT NULL DEFAULT 'open',
                    assigned_to BIGINT UNSIGNED NULL,
                    sla_due_at TIMESTAMP NULL,
                    resolved_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_crm_tickets_tenant (tenant_id),
                    INDEX idx_crm_tickets_status (status),
                    INDEX idx_crm_tickets_priority (priority)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->statement("
                CREATE TABLE IF NOT EXISTS crm_ticket_replies (
                    id {$autoInc},
                    ticket_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NULL,
                    sender_type VARCHAR(20) NOT NULL DEFAULT 'agent',
                    message TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_crm_replies_ticket (ticket_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS crm_ticket_replies");
        $db->statement("DROP TABLE IF EXISTS crm_tickets");
        $db->statement("DROP TABLE IF EXISTS crm_deals");
        $db->statement("DROP TABLE IF EXISTS crm_leads");
    }
};
