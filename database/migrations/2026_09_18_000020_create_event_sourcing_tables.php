<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000020_create_event_sourcing_tables.php
| Module    : EventSourcing
| Namespace : N/A (Migration)
| Purpose   : Creates event_streams and stored_events tables for Event Sourcing & CQRS.
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
        // event_streams — স্ট্রিমের মেটাডাটা ও কারেন্ট ভার্সন ট্র্যাকিং
        // ------------------------------------------------------------------
        $conn->statement("
            CREATE TABLE IF NOT EXISTS event_streams (
                id              INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                stream_id       TEXT    NOT NULL UNIQUE,
                aggregate_type  TEXT    NOT NULL,
                current_version INTEGER NOT NULL DEFAULT 0,
                created_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_event_streams_aggregate
                ON event_streams (aggregate_type)
        ");

        // ------------------------------------------------------------------
        // stored_events — ইমিউটেবল ডোমেন ইভেন্ট লগ
        // ------------------------------------------------------------------
        $conn->statement("
            CREATE TABLE IF NOT EXISTS stored_events (
                id              INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                event_id        TEXT    NOT NULL UNIQUE,
                stream_id       TEXT    NOT NULL,
                aggregate_type  TEXT    NOT NULL,
                version         INTEGER NOT NULL,
                event_name      TEXT    NOT NULL,
                payload         TEXT    NOT NULL DEFAULT '{}',
                metadata        TEXT    NOT NULL DEFAULT '{}',
                occurred_at     TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_stored_events_stream_version
                ON stored_events (stream_id, version)
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_stored_events_aggregate
                ON stored_events (aggregate_type, event_name)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement("DROP TABLE IF EXISTS stored_events");
        $conn->statement("DROP TABLE IF EXISTS event_streams");
    }
};