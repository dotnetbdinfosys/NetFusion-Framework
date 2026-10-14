<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000101_create_superconductivity_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS supercond_materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            material_code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            material_type VARCHAR(32) NOT NULL,
            tc_kelvin REAL NOT NULL,
            bc2_tesla REAL NOT NULL,
            lambda0_nm REAL NOT NULL,
            xi0_nm REAL NOT NULL,
            kappa REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS supercond_junctions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            junction_id VARCHAR(191) NOT NULL UNIQUE,
            critical_current_ua REAL NOT NULL,
            normal_resistance_ohm REAL NOT NULL,
            capacitance_pf REAL NOT NULL,
            beta_c REAL NOT NULL,
            is_overdamped INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_supercond_type ON supercond_materials (material_type)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS supercond_junctions");
        $pdo->exec("DROP TABLE IF EXISTS supercond_materials");
    }
};
