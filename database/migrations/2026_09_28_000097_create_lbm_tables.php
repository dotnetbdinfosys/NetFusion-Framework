<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000097_create_lbm_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS lbm_simulations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            simulation_id VARCHAR(191) NOT NULL UNIQUE,
            lattice_model VARCHAR(32) NOT NULL,
            grid_width INTEGER NOT NULL,
            grid_height INTEGER NOT NULL,
            tau REAL NOT NULL,
            viscosity REAL NOT NULL,
            total_steps INTEGER NOT NULL DEFAULT 0,
            reynolds_number REAL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS lbm_field_snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            simulation_id VARCHAR(191) NOT NULL,
            step_index INTEGER NOT NULL,
            max_speed REAL NOT NULL,
            avg_density REAL NOT NULL,
            vorticity_min REAL,
            vorticity_max REAL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lbm_snapshots_sim ON lbm_field_snapshots (simulation_id)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS lbm_field_snapshots");
        $pdo->exec("DROP TABLE IF EXISTS lbm_simulations");
    }
};
