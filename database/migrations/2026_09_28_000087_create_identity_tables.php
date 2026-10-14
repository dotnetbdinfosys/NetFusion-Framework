<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000087_create_identity_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS identity_dids (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            did VARCHAR(255) NOT NULL UNIQUE,
            method VARCHAR(50) NOT NULL DEFAULT 'netfusion',
            document_json TEXT NOT NULL,
            deactivated INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS identity_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            credential_id VARCHAR(255) NOT NULL UNIQUE,
            issuer_did VARCHAR(255) NOT NULL,
            subject_did VARCHAR(255),
            credential_type VARCHAR(100) NOT NULL DEFAULT 'VerifiableCredential',
            issuance_date VARCHAR(50) NOT NULL,
            expiration_date VARCHAR(50),
            revoked INTEGER NOT NULL DEFAULT 0,
            credential_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS identity_trust_registry (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ecosystem_id VARCHAR(191) NOT NULL,
            issuer_did VARCHAR(255) NOT NULL,
            schema_uri VARCHAR(255) NOT NULL,
            state VARCHAR(50) NOT NULL DEFAULT 'authorized',
            valid_until VARCHAR(50),
            jurisdiction VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS identity_trust_registry");
        $pdo->exec("DROP TABLE IF EXISTS identity_credentials");
        $pdo->exec("DROP TABLE IF EXISTS identity_dids");
    }
};
