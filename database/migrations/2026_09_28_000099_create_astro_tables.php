<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000099_create_astro_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS astro_celestial_bodies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            body_id VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            body_type VARCHAR(64) NOT NULL,
            mass REAL NOT NULL,
            radius REAL NOT NULL,
            pos_x REAL NOT NULL DEFAULT 0.0,
            pos_y REAL NOT NULL DEFAULT 0.0,
            pos_z REAL NOT NULL DEFAULT 0.0,
            vel_x REAL NOT NULL DEFAULT 0.0,
            vel_y REAL NOT NULL DEFAULT 0.0,
            vel_z REAL NOT NULL DEFAULT 0.0,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS astro_ephemeris_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            body_id VARCHAR(191) NOT NULL,
            epoch_time REAL NOT NULL,
            pos_x REAL NOT NULL,
            pos_y REAL NOT NULL,
            pos_z REAL NOT NULL,
            vel_x REAL NOT NULL,
            vel_y REAL NOT NULL,
            vel_z REAL NOT NULL,
            speed REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_astro_ephemeris_body ON astro_ephemeris_records (body_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_astro_ephemeris_epoch ON astro_ephemeris_records (epoch_time)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS astro_ephemeris_records");
        $pdo->exec("DROP TABLE IF EXISTS astro_celestial_bodies");
    }
};
