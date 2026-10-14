<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000088_create_spatial_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS spatial_scenes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            scene_id VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            total_nodes INTEGER NOT NULL DEFAULT 1,
            scene_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS spatial_nodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            node_id VARCHAR(191) NOT NULL,
            scene_id VARCHAR(191) NOT NULL,
            node_type VARCHAR(50) NOT NULL DEFAULT 'group',
            parent_id VARCHAR(191),
            transform_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS spatial_digital_twins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            twin_id VARCHAR(191) NOT NULL UNIQUE,
            scene_id VARCHAR(191) NOT NULL,
            node_id VARCHAR(191) NOT NULL,
            telemetry_json TEXT NOT NULL,
            last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS spatial_digital_twins");
        $pdo->exec("DROP TABLE IF EXISTS spatial_nodes");
        $pdo->exec("DROP TABLE IF EXISTS spatial_scenes");
    }
};
