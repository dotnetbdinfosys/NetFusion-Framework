<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000094_create_tda_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS tda_complexes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            complex_id VARCHAR(191) NOT NULL UNIQUE,
            num_points INTEGER NOT NULL,
            num_simplices INTEGER NOT NULL,
            max_dimension INTEGER NOT NULL DEFAULT 2,
            max_filtration REAL NOT NULL,
            metric VARCHAR(32) NOT NULL DEFAULT 'euclidean',
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tda_barcodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            complex_id VARCHAR(191) NOT NULL,
            dimension INTEGER NOT NULL,
            birth REAL NOT NULL,
            death REAL,
            lifespan REAL,
            is_infinite INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tda_barcodes_complex ON tda_barcodes (complex_id)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS tda_barcodes");
        $pdo->exec("DROP TABLE IF EXISTS tda_complexes");
    }
};
