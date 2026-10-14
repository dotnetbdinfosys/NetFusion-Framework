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

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migrations.
     */
    public function up(Connection $connection): void
    {
        // 1. Data Warehouse Cubes Metadata
        $connection->statement("
            CREATE TABLE IF NOT EXISTS nf_dw_cubes (
                name VARCHAR(100) PRIMARY KEY,
                fact_table VARCHAR(100) NOT NULL,
                measures_json TEXT NOT NULL,
                hierarchies_json TEXT,
                total_rows INT DEFAULT 0,
                updated_at DATETIME
            )
        ");

        // 2. Saved BI Pivot Reports
        $connection->statement("
            CREATE TABLE IF NOT EXISTS nf_dw_reports (
                id VARCHAR(100) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                cube_name VARCHAR(100) NOT NULL,
                config_json TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $connection): void
    {
        $connection->statement("DROP TABLE IF EXISTS nf_dw_reports");
        $connection->statement("DROP TABLE IF EXISTS nf_dw_cubes");
    }
};
