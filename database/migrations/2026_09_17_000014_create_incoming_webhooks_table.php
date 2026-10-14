<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000014_create_incoming_webhooks_table.php
| Module    : Webhooks
| Namespace : None (Database Migration)
| Purpose   : Database migration creating incoming webhook events, idempotency, and audit ledger.
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
                CREATE TABLE IF NOT EXISTS incoming_webhooks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    gateway TEXT NOT NULL,
                    event_id TEXT NULL,
                    event_type TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    headers TEXT NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    attempts INTEGER NOT NULL DEFAULT 0,
                    error_message TEXT NULL,
                    processed_at TEXT NULL,
                    created_at TEXT NOT NULL
                )
            ");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_in_webhook_gateway ON incoming_webhooks(gateway)");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_in_webhook_idempotency ON incoming_webhooks(gateway, event_id)");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_in_webhook_status ON incoming_webhooks(status)");
        } else {
            $db->statement("
                CREATE TABLE IF NOT EXISTS incoming_webhooks (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    gateway VARCHAR(50) NOT NULL,
                    event_id VARCHAR(191) NULL,
                    event_type VARCHAR(100) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    headers JSON NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'pending',
                    attempts INT NOT NULL DEFAULT 0,
                    error_message TEXT NULL,
                    processed_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_in_webhook_gateway (gateway),
                    INDEX idx_in_webhook_idempotency (gateway, event_id),
                    INDEX idx_in_webhook_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS incoming_webhooks");
    }
};
