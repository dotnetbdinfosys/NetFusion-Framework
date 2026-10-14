<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000093_create_relativity_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS relativity_chirps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            simulation_id VARCHAR(191) NOT NULL UNIQUE,
            m1_solar REAL NOT NULL,
            m2_solar REAL NOT NULL,
            chirp_mass_solar REAL NOT NULL,
            distance_mpc REAL NOT NULL,
            peak_strain REAL NOT NULL,
            isco_frequency_hz REAL NOT NULL,
            ringdown_frequency_hz REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS relativity_detections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id VARCHAR(191) NOT NULL UNIQUE,
            detector_site VARCHAR(32) NOT NULL DEFAULT 'H1',
            snr REAL NOT NULL,
            is_significant INTEGER NOT NULL DEFAULT 1,
            matched_template_id VARCHAR(191) NOT NULL,
            detected_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_relativity_detections_site ON relativity_detections (detector_site)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS relativity_detections");
        $pdo->exec("DROP TABLE IF EXISTS relativity_chirps");
    }
};
