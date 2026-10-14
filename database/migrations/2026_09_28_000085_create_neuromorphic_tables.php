<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000085_create_neuromorphic_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS neuromorphic_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_name VARCHAR(191) NOT NULL UNIQUE,
            input_dim INTEGER NOT NULL DEFAULT 4,
            layers_json TEXT NOT NULL,
            accuracy REAL NOT NULL DEFAULT 0.0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS neuromorphic_spikes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_name VARCHAR(191) NOT NULL,
            timesteps INTEGER NOT NULL DEFAULT 30,
            total_spikes INTEGER NOT NULL DEFAULT 0,
            synaptic_operations INTEGER NOT NULL DEFAULT 0,
            predicted_class INTEGER NOT NULL DEFAULT 0,
            confidence REAL NOT NULL DEFAULT 0.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS neuromorphic_benchmarks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            neurons INTEGER NOT NULL DEFAULT 50,
            timesteps INTEGER NOT NULL DEFAULT 50,
            total_sops INTEGER NOT NULL DEFAULT 0,
            sops_per_second REAL NOT NULL DEFAULT 0.0,
            spike_sparsity REAL NOT NULL DEFAULT 0.0,
            elapsed_ms REAL NOT NULL DEFAULT 0.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS neuromorphic_benchmarks");
        $pdo->exec("DROP TABLE IF EXISTS neuromorphic_spikes");
        $pdo->exec("DROP TABLE IF EXISTS neuromorphic_models");
    }
};
