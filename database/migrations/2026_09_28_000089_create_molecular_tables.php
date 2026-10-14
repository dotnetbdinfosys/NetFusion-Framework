<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000089_create_molecular_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS molecular_vaults (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            vault_id VARCHAR(191) NOT NULL UNIQUE,
            facility VARCHAR(255) NOT NULL DEFAULT 'Svalbard_Molecular_Vault',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS dna_oligo_pools (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id VARCHAR(191) NOT NULL UNIQUE,
            vault_id VARCHAR(191),
            total_oligos INTEGER NOT NULL DEFAULT 0,
            pool_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS holographic_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            crystal_id VARCHAR(191) NOT NULL,
            page_index INTEGER NOT NULL,
            bragg_angle REAL NOT NULL,
            page_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS holographic_pages");
        $pdo->exec("DROP TABLE IF EXISTS dna_oligo_pools");
        $pdo->exec("DROP TABLE IF EXISTS molecular_vaults");
    }
};
