<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000080_create_differential_privacy_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS dp_privacy_budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier VARCHAR(191) NOT NULL UNIQUE,
            total_epsilon REAL NOT NULL DEFAULT 1.0,
            consumed_epsilon REAL NOT NULL DEFAULT 0.0,
            total_delta REAL NOT NULL DEFAULT 0.00001,
            consumed_delta REAL NOT NULL DEFAULT 0.0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS dp_query_audits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_identifier VARCHAR(191) NOT NULL,
            query_type VARCHAR(50) NOT NULL,
            epsilon_spent REAL NOT NULL,
            delta_spent REAL NOT NULL DEFAULT 0.0,
            mechanism VARCHAR(50) NOT NULL,
            noise_scale REAL NOT NULL DEFAULT 0.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS dp_federated_rounds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            round_number INTEGER NOT NULL UNIQUE,
            participating_nodes INTEGER NOT NULL,
            clipping_norm REAL NOT NULL,
            epsilon_allocated REAL NOT NULL,
            global_loss REAL NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS dp_federated_rounds");
        $pdo->exec("DROP TABLE IF EXISTS dp_query_audits");
        $pdo->exec("DROP TABLE IF EXISTS dp_privacy_budgets");
    }
};
