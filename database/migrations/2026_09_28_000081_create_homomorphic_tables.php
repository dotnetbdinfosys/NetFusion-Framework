<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000081_create_homomorphic_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS he_keypairs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_identifier VARCHAR(191) NOT NULL UNIQUE,
            scheme VARCHAR(50) NOT NULL DEFAULT 'bfv',
            degree INTEGER NOT NULL DEFAULT 16,
            modulus INTEGER NOT NULL DEFAULT 65537,
            public_key_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS he_ciphertexts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cipher_label VARCHAR(191) NOT NULL,
            scheme VARCHAR(50) NOT NULL DEFAULT 'bfv',
            elements_json TEXT NOT NULL,
            noise_estimate INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS smpc_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(191) NOT NULL UNIQUE,
            threshold_k INTEGER NOT NULL,
            total_n INTEGER NOT NULL,
            prime INTEGER NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS smpc_sessions");
        $pdo->exec("DROP TABLE IF EXISTS he_ciphertexts");
        $pdo->exec("DROP TABLE IF EXISTS he_keypairs");
    }
};
