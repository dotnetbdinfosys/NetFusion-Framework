<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000092_create_ris_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS ris_surfaces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            surface_id VARCHAR(191) NOT NULL UNIQUE,
            frequency_band VARCHAR(32) NOT NULL DEFAULT 'terahertz',
            phase_resolution VARCHAR(32) NOT NULL DEFAULT 'continuous',
            rows INTEGER NOT NULL DEFAULT 8,
            cols INTEGER NOT NULL DEFAULT 8,
            spacing_meters REAL,
            current_phase_profile TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ris_telemetry (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            surface_id VARCHAR(191) NOT NULL,
            direct_capacity_bps_hz REAL NOT NULL,
            effective_capacity_bps_hz REAL NOT NULL,
            snr_gain_db REAL NOT NULL,
            quantization_error_rad REAL NOT NULL,
            algorithm VARCHAR(32) NOT NULL DEFAULT 'mrt',
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ris_telemetry_surface ON ris_telemetry (surface_id)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS ris_telemetry");
        $pdo->exec("DROP TABLE IF EXISTS ris_surfaces");
    }
};
