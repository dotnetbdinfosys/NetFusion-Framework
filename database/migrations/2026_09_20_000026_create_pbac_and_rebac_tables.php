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
| File      : database/migrations/2026_09_20_000026_create_pbac_and_rebac_tables.php
| Module    : Policy-Based Access Control (PBAC) & ReBAC
| Namespace : N/A (Migration)
| Purpose   : Creates tables for PBAC authorization policies and ReBAC Zanzibar relationship tuples.
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
    public function up(mixed $conn): void
    {
        $exec = function(string $sql) use ($conn) {
            if ($conn instanceof \PDO) {
                $conn->exec($sql);
            } else {
                $conn->statement($sql);
            }
        };

        // 1. Policies Table
        $exec("
            CREATE TABLE IF NOT EXISTS netfusion_policies (
                id          VARCHAR(191) NOT NULL PRIMARY KEY,
                name        VARCHAR(191) NOT NULL,
                priority    INTEGER      NOT NULL DEFAULT 0,
                enabled     INTEGER      NOT NULL DEFAULT 1,
                description TEXT         NULL,
                rules       TEXT         NOT NULL,
                created_at  TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at  TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $exec("
            CREATE INDEX IF NOT EXISTS idx_policies_enabled_priority
                ON netfusion_policies (enabled, priority DESC)
        ");

        // 2. ReBAC Zanzibar Tuples Table
        $exec("
            CREATE TABLE IF NOT EXISTS netfusion_rebac_tuples (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id        VARCHAR(191) NULL,
                entity_type      VARCHAR(100) NOT NULL,
                entity_id        VARCHAR(191) NOT NULL,
                relation         VARCHAR(100) NOT NULL,
                subject_type     VARCHAR(100) NOT NULL,
                subject_id       VARCHAR(191) NOT NULL,
                subject_relation VARCHAR(100) NULL,
                created_at       TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                UNIQUE(entity_type, entity_id, relation, subject_type, subject_id, subject_relation)
            )
        ");

        $exec("
            CREATE INDEX IF NOT EXISTS idx_rebac_entity
                ON netfusion_rebac_tuples (entity_type, entity_id, relation)
        ");

        $exec("
            CREATE INDEX IF NOT EXISTS idx_rebac_subject
                ON netfusion_rebac_tuples (subject_type, subject_id)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(mixed $conn): void
    {
        $exec = function(string $sql) use ($conn) {
            if ($conn instanceof \PDO) {
                $conn->exec($sql);
            } else {
                $conn->statement($sql);
            }
        };

        $exec("DROP TABLE IF EXISTS netfusion_rebac_tuples");
        $exec("DROP TABLE IF EXISTS netfusion_policies");
    }
};
