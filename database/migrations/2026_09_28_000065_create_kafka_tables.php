<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
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

return new class {
    public function up(Connection $db): void
    {
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS kafka_topics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                partitions_count INTEGER NOT NULL DEFAULT 1,
                replication_factor INTEGER NOT NULL DEFAULT 1,
                config TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS kafka_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic TEXT NOT NULL,
                partition INTEGER NOT NULL,
                offset INTEGER NOT NULL,
                key TEXT,
                value TEXT NOT NULL,
                headers TEXT,
                timestamp INTEGER NOT NULL,
                producer_id INTEGER DEFAULT 0,
                producer_epoch INTEGER DEFAULT 0,
                sequence_number INTEGER DEFAULT 0,
                is_control INTEGER DEFAULT 0,
                control_type TEXT,
                created_at INTEGER NOT NULL,
                UNIQUE(topic, partition, offset)
            );

            CREATE INDEX IF NOT EXISTS idx_kafka_topic_part ON kafka_records(topic, partition, offset);

            CREATE TABLE IF NOT EXISTS kafka_consumer_offsets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id TEXT NOT NULL,
                topic TEXT NOT NULL,
                partition INTEGER NOT NULL,
                committed_offset INTEGER NOT NULL,
                leader_epoch INTEGER DEFAULT 0,
                metadata TEXT,
                committed_at INTEGER NOT NULL,
                UNIQUE(group_id, topic, partition)
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS kafka_records;
            DROP TABLE IF EXISTS kafka_consumer_offsets;
            DROP TABLE IF EXISTS kafka_topics;
        ");
    }
};
