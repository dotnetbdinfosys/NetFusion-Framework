<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000091_create_photonic_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS photonic_devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id VARCHAR(191) NOT NULL UNIQUE,
            wavelength VARCHAR(32) NOT NULL DEFAULT '1550nm',
            chip_material VARCHAR(64) NOT NULL DEFAULT 'Silicon_on_Insulator_SOI',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS photonic_mzi_meshes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mesh_id VARCHAR(191) NOT NULL UNIQUE,
            device_id VARCHAR(191),
            topology VARCHAR(32) NOT NULL DEFAULT 'clements',
            num_ports INTEGER NOT NULL DEFAULT 4,
            cell_count INTEGER NOT NULL DEFAULT 6,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS photonic_inferences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inference_id VARCHAR(191) NOT NULL UNIQUE,
            mesh_id VARCHAR(191) NOT NULL,
            dimension INTEGER NOT NULL DEFAULT 4,
            latency_ps REAL NOT NULL DEFAULT 20.0,
            energy_fj REAL NOT NULL DEFAULT 136.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS photonic_inferences");
        $pdo->exec("DROP TABLE IF EXISTS photonic_mzi_meshes");
        $pdo->exec("DROP TABLE IF EXISTS photonic_devices");
    }
};
