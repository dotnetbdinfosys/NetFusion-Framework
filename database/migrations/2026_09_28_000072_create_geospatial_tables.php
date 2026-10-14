<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Geospatial Tables Migration
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
        // 1. Defined Geo-Fences (Polygon boundaries & Circular buffers)
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS spatial_geofences (
                id VARCHAR(64) PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'polygon',
                geometry_geojson TEXT NOT NULL,
                radius_meters REAL NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Tracked Spatial Entities (Vehicles, Couriers, Mobile Users)
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS spatial_tracked_entities (
                id VARCHAR(64) PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                last_lat REAL NOT NULL,
                last_lng REAL NOT NULL,
                last_h3 VARCHAR(16) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Geo-Fence Telemetry Event Logs (ENTER, DWELL, EXIT)
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS spatial_geofence_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_id VARCHAR(64) NOT NULL,
                fence_id VARCHAR(64) NOT NULL,
                event VARCHAR(20) NOT NULL,
                lat REAL NOT NULL,
                lng REAL NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("DROP TABLE IF EXISTS spatial_geofence_events;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS spatial_tracked_entities;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS spatial_geofences;");
    }
};
