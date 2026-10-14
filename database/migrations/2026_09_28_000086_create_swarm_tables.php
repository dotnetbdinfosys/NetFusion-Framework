<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000086_create_swarm_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS swarm_colonies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            colony_id VARCHAR(191) NOT NULL UNIQUE,
            grid_width INTEGER NOT NULL DEFAULT 20,
            grid_height INTEGER NOT NULL DEFAULT 20,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS swarm_agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id VARCHAR(191) NOT NULL UNIQUE,
            colony_id VARCHAR(191) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'worker',
            x INTEGER NOT NULL DEFAULT 0,
            y INTEGER NOT NULL DEFAULT 0,
            reputation REAL NOT NULL DEFAULT 1.0,
            tasks_completed INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS swarm_auctions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id VARCHAR(191) NOT NULL UNIQUE,
            colony_id VARCHAR(191) NOT NULL,
            awarded_agent VARCHAR(191),
            winning_score REAL NOT NULL DEFAULT 0.0,
            total_bids INTEGER NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS swarm_auctions");
        $pdo->exec("DROP TABLE IF EXISTS swarm_agents");
        $pdo->exec("DROP TABLE IF EXISTS swarm_colonies");
    }
};
