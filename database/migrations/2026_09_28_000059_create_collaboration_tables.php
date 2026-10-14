<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Migration
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\Migrations\Migration;

/**
 * Migration: create_collaboration_tables
 *
 * Tables:
 *   - collab_documents : Master registry of collaborative documents and current revision
 *   - collab_snapshots : Cryptographically verified point-in-time CRDT/OT state snapshots
 *   - collab_peers     : Active signaling peers and real-time awareness presence
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS collab_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_id VARCHAR(160) UNIQUE NOT NULL,
                title VARCHAR(255) NULL,
                revision INTEGER DEFAULT 0,
                content_blob TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS collab_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_id VARCHAR(160) NOT NULL,
                revision INTEGER NOT NULL,
                snapshot_blob TEXT NOT NULL,
                checksum VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS collab_peers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id VARCHAR(160) NOT NULL,
                peer_id VARCHAR(120) NOT NULL,
                user_name VARCHAR(120) NULL,
                cursor_json TEXT NULL,
                last_seen TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS collab_peers");
        $db->statement("DROP TABLE IF EXISTS collab_snapshots");
        $db->statement("DROP TABLE IF EXISTS collab_documents");
    }
};
