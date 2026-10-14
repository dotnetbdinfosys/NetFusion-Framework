<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000083_create_confidential_enclave_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS enclave_registrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            enclave_id VARCHAR(191) NOT NULL UNIQUE,
            enclave_type VARCHAR(50) NOT NULL DEFAULT 'intel_sgx',
            mr_enclave VARCHAR(191) NOT NULL,
            mr_signer VARCHAR(191) NOT NULL,
            isv_prod_id INTEGER NOT NULL DEFAULT 1,
            isv_svn INTEGER NOT NULL DEFAULT 1,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS enclave_attestation_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quote_id VARCHAR(191) NOT NULL UNIQUE,
            enclave_id VARCHAR(191) NOT NULL,
            user_data VARCHAR(191) NOT NULL,
            verification_status VARCHAR(50) NOT NULL DEFAULT 'verified',
            verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS enclave_sealed_vaults (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            secret_label VARCHAR(191) NOT NULL,
            enclave_id VARCHAR(191) NOT NULL,
            measurement_digest VARCHAR(191) NOT NULL,
            ciphertext_hex TEXT NOT NULL,
            nonce_hex VARCHAR(50) NOT NULL,
            tag_hex VARCHAR(50) NOT NULL,
            salt_hex VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS enclave_sealed_vaults");
        $pdo->exec("DROP TABLE IF EXISTS enclave_attestation_reports");
        $pdo->exec("DROP TABLE IF EXISTS enclave_registrations");
    }
};
