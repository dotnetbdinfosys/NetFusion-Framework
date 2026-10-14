<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000100_create_plasma_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS plasma_discharges (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shot_number VARCHAR(191) NOT NULL UNIQUE,
            major_radius REAL NOT NULL,
            minor_radius REAL NOT NULL,
            b0_tesla REAL NOT NULL,
            ip_ma REAL NOT NULL,
            fuel_mixture VARCHAR(32) NOT NULL,
            edge_q REAL NOT NULL,
            beta_t REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS plasma_diagnostics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shot_number VARCHAR(191) NOT NULL,
            time_slice REAL NOT NULL,
            density_e20 REAL NOT NULL,
            temperature_kev REAL NOT NULL,
            confinement_time_sec REAL NOT NULL,
            fusion_power_mw REAL NOT NULL,
            q_gain REAL NOT NULL,
            is_ignited INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_plasma_diag_shot ON plasma_diagnostics (shot_number)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_plasma_diag_time ON plasma_diagnostics (time_slice)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS plasma_diagnostics");
        $pdo->exec("DROP TABLE IF EXISTS plasma_discharges");
    }
};
