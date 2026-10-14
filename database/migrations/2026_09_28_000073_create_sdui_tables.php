<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Server-Driven UI (SDUI) Tables Migration
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
        // 1. Registered Server-Driven UI Screens
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS sdui_screens (
                id VARCHAR(64) PRIMARY KEY,
                screen_key VARCHAR(120) NOT NULL UNIQUE,
                version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                title VARCHAR(255) NOT NULL,
                layout_schema TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Reusable Modular UI Components
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS sdui_components (
                id VARCHAR(64) PRIMARY KEY,
                component_key VARCHAR(120) NOT NULL UNIQUE,
                type VARCHAR(50) NOT NULL,
                schema_definition TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Granular Delta Patches for Screen Versions (RFC 6902)
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS sdui_screen_patches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                screen_id VARCHAR(64) NOT NULL,
                patch_version VARCHAR(20) NOT NULL,
                patch_delta TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("DROP TABLE IF EXISTS sdui_screen_patches;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS sdui_components;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS sdui_screens;");
    }
};
