<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - WebRTC Media Server & SFU Tables Migration
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        // 1. Active & Historical SFU Conference Rooms
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS media_rooms (
                id VARCHAR(64) PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                max_peers INTEGER NOT NULL DEFAULT 100,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Peer Participants & Sessions
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS media_peers (
                id VARCHAR(64) PRIMARY KEY,
                room_id VARCHAR(64) NOT NULL,
                name VARCHAR(120) NOT NULL,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Audio/Video Track Recordings & ABR Assets
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS media_recordings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id VARCHAR(64) NOT NULL,
                track_id VARCHAR(64) NOT NULL,
                codec VARCHAR(20) NOT NULL,
                file_path TEXT NOT NULL,
                duration_seconds REAL NOT NULL DEFAULT 0.0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("DROP TABLE IF EXISTS media_recordings;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS media_peers;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS media_rooms;");
    }
};
