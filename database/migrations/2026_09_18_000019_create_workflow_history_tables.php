<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000019_create_workflow_history_tables.php
| Module    : Workflow
| Namespace : N/A (Migration)
| Purpose   : Creates workflow_histories and approval_requests tables.
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
        // ------------------------------------------------------------------
        // workflow_histories — স্টেট ট্রানজিশনের অডিট হিস্ট্রি
        // ------------------------------------------------------------------
        $conn->statement("
            CREATE TABLE IF NOT EXISTS workflow_histories (
                id              INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                entity_type     TEXT    NOT NULL,
                entity_id       INTEGER NOT NULL,
                workflow_name   TEXT    NOT NULL,
                from_state      TEXT    NULL,
                to_state        TEXT    NOT NULL,
                transition_name TEXT    NOT NULL,
                actor_id        INTEGER NULL,
                actor_type      TEXT    NULL,
                comments        TEXT    NULL,
                created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_workflow_history_entity
                ON workflow_histories (entity_type, entity_id)
        ");

        // ------------------------------------------------------------------
        // approval_requests — মাল্টি-লেভেল অ্যাপ্রুভাল পাইপলাইনের রিকোয়েস্ট
        // ------------------------------------------------------------------
        $conn->statement("
            CREATE TABLE IF NOT EXISTS approval_requests (
                id            INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                entity_type   TEXT    NOT NULL,
                entity_id     INTEGER NOT NULL,
                pipeline_name TEXT    NOT NULL,
                stage_index   INTEGER NOT NULL DEFAULT 0,
                stage_name    TEXT    NOT NULL,
                approver_id   INTEGER NULL,
                status        TEXT    NOT NULL DEFAULT 'pending',
                comments      TEXT    NULL,
                acted_at      TEXT    NULL,
                created_at    TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_approval_requests_entity
                ON approval_requests (entity_type, entity_id, status)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement('DROP TABLE IF EXISTS approval_requests');
        $conn->statement('DROP TABLE IF EXISTS workflow_histories');
    }
};