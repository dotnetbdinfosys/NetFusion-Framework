<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000084_create_qkd_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS qkd_nodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            node_id VARCHAR(191) NOT NULL UNIQUE,
            public_endpoint VARCHAR(191) NOT NULL,
            protocol_type VARCHAR(50) NOT NULL DEFAULT 'bb84',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS qkd_key_pools (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            peer_id VARCHAR(191) NOT NULL,
            key_hex VARCHAR(191) NOT NULL,
            key_size_bits INTEGER NOT NULL DEFAULT 256,
            status VARCHAR(50) NOT NULL DEFAULT 'available',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS qkd_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(191) NOT NULL UNIQUE,
            peer_a VARCHAR(191) NOT NULL,
            peer_b VARCHAR(191) NOT NULL,
            protocol VARCHAR(50) NOT NULL DEFAULT 'bb84',
            qber REAL NOT NULL DEFAULT 0.0,
            photons_exchanged INTEGER NOT NULL DEFAULT 256,
            status VARCHAR(50) NOT NULL DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS qkd_sessions");
        $pdo->exec("DROP TABLE IF EXISTS qkd_key_pools");
        $pdo->exec("DROP TABLE IF EXISTS qkd_nodes");
    }
};
