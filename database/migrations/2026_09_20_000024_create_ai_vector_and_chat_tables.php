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
| File      : database/migrations/2026_09_20_000024_create_ai_vector_and_chat_tables.php
| Module    : AI Agent & Vector Memory
| Namespace : N/A (Migration)
| Purpose   : Creates tables for high-dimensional vector embeddings and chat sessions.
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
        // 1. Vector Documents Table
        $conn->statement("
            CREATE TABLE IF NOT EXISTS ai_vector_documents (
                id           VARCHAR(191) NOT NULL,
                collection   VARCHAR(100) NOT NULL DEFAULT 'default',
                vector       TEXT         NOT NULL,
                content      TEXT         NULL,
                metadata     TEXT         NULL,
                created_at   TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at   TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                PRIMARY KEY (id, collection)
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_ai_vector_collection
                ON ai_vector_documents (collection)
        ");

        // 2. Chat Histories Table
        $conn->statement("
            CREATE TABLE IF NOT EXISTS ai_chat_histories (
                id           INTEGER      PRIMARY KEY AUTOINCREMENT,
                session_id   VARCHAR(191) NOT NULL,
                role         VARCHAR(50)  NOT NULL,
                content      TEXT         NULL,
                tool_calls   TEXT         NULL,
                tool_call_id VARCHAR(191) NULL,
                metadata     TEXT         NULL,
                created_at   TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_ai_chat_session
                ON ai_chat_histories (session_id, created_at)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement("DROP TABLE IF EXISTS ai_chat_histories");
        $conn->statement("DROP TABLE IF EXISTS ai_vector_documents");
    }
};
