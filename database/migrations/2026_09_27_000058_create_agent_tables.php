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
 * Migration: create_agent_tables
 *
 * Tables:
 *   - agent_runs           : Audit log of autonomous ReAct agent execution runs
 *   - agent_thoughts       : Detailed ReAct iteration steps (thought, action, observation)
 *   - agent_memory_entries : Long-term semantic & working memory persistence
 */
return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS agent_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id VARCHAR(64) UNIQUE NOT NULL,
                agent_name VARCHAR(120) NOT NULL,
                task TEXT NOT NULL,
                status VARCHAR(32) NOT NULL,
                total_iterations INTEGER DEFAULT 0,
                tool_calls_count INTEGER DEFAULT 0,
                final_answer TEXT NULL,
                duration_ms REAL DEFAULT 0,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS agent_thoughts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id VARCHAR(64) NOT NULL,
                iteration INTEGER NOT NULL,
                thought TEXT NULL,
                action VARCHAR(120) NULL,
                action_input TEXT NULL,
                observation TEXT NULL,
                is_final INTEGER DEFAULT 0,
                created_at TIMESTAMP NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS agent_memory_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_name VARCHAR(120) NOT NULL,
                memory_type VARCHAR(32) NOT NULL,
                memory_key VARCHAR(160) NULL,
                content TEXT NOT NULL,
                metadata_json TEXT NULL,
                created_at TIMESTAMP NULL
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS agent_memory_entries");
        $db->statement("DROP TABLE IF EXISTS agent_thoughts");
        $db->statement("DROP TABLE IF EXISTS agent_runs");
    }
};
