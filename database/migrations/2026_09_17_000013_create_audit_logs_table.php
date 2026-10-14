<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_17_000013_create_audit_logs_table.php
| Module    : Audit
| Namespace : None (Database Migration)
| Purpose   : Database migration creating enterprise audit logs and data revision history table.
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
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NULL,
                    tenant_id INTEGER NULL,
                    event TEXT NOT NULL,
                    auditable_type TEXT NOT NULL,
                    auditable_id INTEGER NOT NULL,
                    old_values TEXT NULL,
                    new_values TEXT NULL,
                    ip_address TEXT NULL,
                    user_agent TEXT NULL,
                    url TEXT NULL,
                    tags TEXT NULL,
                    created_at TEXT NOT NULL
                )
            ");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_audit_auditable ON audit_logs(auditable_type, auditable_id)");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user_id)");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_audit_event ON audit_logs(event)");
            $db->statement("CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs(created_at)");
        } else {
            // MySQL, PostgreSQL, MariaDB
            $db->statement("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NULL,
                    tenant_id BIGINT UNSIGNED NULL,
                    event VARCHAR(50) NOT NULL,
                    auditable_type VARCHAR(255) NOT NULL,
                    auditable_id BIGINT UNSIGNED NOT NULL,
                    old_values JSON NULL,
                    new_values JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    url TEXT NULL,
                    tags VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_audit_auditable (auditable_type, auditable_id),
                    INDEX idx_audit_user (user_id),
                    INDEX idx_audit_event (event),
                    INDEX idx_audit_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS audit_logs");
    }
};
