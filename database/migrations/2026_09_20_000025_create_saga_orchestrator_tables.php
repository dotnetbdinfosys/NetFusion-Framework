<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_20_000025_create_saga_orchestrator_tables.php
| Module    : Saga Orchestrator & Distributed Transactions
| Namespace : N/A (Migration)
| Purpose   : Creates tables for Saga instance execution state and step audit trails.
|
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
    /**
     * Run the migration.
     */
    public function up(Connection $conn): void
    {
        // 1. Saga Instances Table
        $conn->statement("
            CREATE TABLE IF NOT EXISTS netfusion_saga_instances (
                id                 VARCHAR(191) NOT NULL PRIMARY KEY,
                saga_name          VARCHAR(191) NOT NULL,
                state              VARCHAR(50)  NOT NULL DEFAULT 'PENDING',
                context            TEXT         NOT NULL,
                current_step_index INTEGER      NOT NULL DEFAULT 0,
                error              TEXT         NULL,
                started_at         REAL         NOT NULL,
                completed_at       REAL         NULL,
                created_at         TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at         TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_saga_name_state
                ON netfusion_saga_instances (saga_name, state)
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_saga_started_at
                ON netfusion_saga_instances (started_at)
        ");

        // 2. Saga Steps Table
        $conn->statement("
            CREATE TABLE IF NOT EXISTS netfusion_saga_steps (
                id                INTEGER      PRIMARY KEY AUTOINCREMENT,
                saga_id           VARCHAR(191) NOT NULL,
                step_name         VARCHAR(191) NOT NULL,
                success           INTEGER      NOT NULL DEFAULT 0,
                result            TEXT         NULL,
                error             TEXT         NULL,
                compensated       INTEGER      NOT NULL DEFAULT 0,
                execution_time_ms REAL         NOT NULL DEFAULT 0.0,
                created_at        TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                UNIQUE(saga_id, step_name)
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_saga_step_saga_id
                ON netfusion_saga_steps (saga_id)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement("DROP TABLE IF EXISTS netfusion_saga_steps");
        $conn->statement("DROP TABLE IF EXISTS netfusion_saga_instances");
    }
};
