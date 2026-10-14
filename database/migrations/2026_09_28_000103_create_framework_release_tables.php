<?php
/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000103_create_framework_release_tables.php
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

return new class {
    public function up(Connection $conn): void
    {
        $pdo = $conn->getPdo();

        // Table: framework_releases — official release deployments history
        $pdo->exec("CREATE TABLE IF NOT EXISTS framework_releases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version VARCHAR(32) NOT NULL UNIQUE,
            codename VARCHAR(191) NOT NULL,
            status VARCHAR(32) NOT NULL,
            total_phases INTEGER NOT NULL,
            certified_at DATETIME NOT NULL
        )");

        // Table: framework_integrity_audits — Golden Master diagnostic audit records
        $pdo->exec("CREATE TABLE IF NOT EXISTS framework_integrity_audits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            audit_id VARCHAR(191) NOT NULL UNIQUE,
            score INTEGER NOT NULL,
            is_certified INTEGER NOT NULL DEFAULT 1,
            diagnostics_json TEXT NOT NULL,
            audited_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_release_version ON framework_releases (version)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS framework_integrity_audits");
        $pdo->exec("DROP TABLE IF EXISTS framework_releases");
    }
};
