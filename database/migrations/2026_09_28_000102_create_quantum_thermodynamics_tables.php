<?php
/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000102_create_quantum_thermodynamics_tables.php
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
    public function up(Connection $conn): void
    {
        $pdo = $conn->getPdo();

        // Table: quantum_open_systems — records Lindblad evolution sessions
        $pdo->exec("CREATE TABLE IF NOT EXISTS quantum_open_systems (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(191) NOT NULL UNIQUE,
            omega_hz REAL NOT NULL,
            dissipation_channels TEXT NOT NULL,
            steps INTEGER NOT NULL,
            dt_seconds REAL NOT NULL,
            initial_rho00 REAL NOT NULL,
            initial_rho11 REAL NOT NULL,
            final_rho00 REAL NOT NULL,
            final_rho11 REAL NOT NULL,
            final_purity REAL NOT NULL,
            final_entropy REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        // Table: quantum_heat_cycles — records quantum heat engine cycle results
        $pdo->exec("CREATE TABLE IF NOT EXISTS quantum_heat_cycles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_id VARCHAR(191) NOT NULL UNIQUE,
            cycle_type VARCHAR(32) NOT NULL,
            omega_cold_hz REAL NOT NULL,
            omega_hot_hz REAL NOT NULL,
            temp_cold_k REAL NOT NULL,
            temp_hot_k REAL NOT NULL,
            q_hot_joules REAL NOT NULL,
            q_cold_joules REAL NOT NULL,
            work_output_joules REAL NOT NULL,
            otto_efficiency REAL NOT NULL,
            carnot_efficiency REAL NOT NULL,
            is_engine INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )");

        // Table: quantum_entropy_audits — records Landauer erasure and entropy events
        $pdo->exec("CREATE TABLE IF NOT EXISTS quantum_entropy_audits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            audit_id VARCHAR(191) NOT NULL UNIQUE,
            operation VARCHAR(64) NOT NULL,
            bits_erased REAL NOT NULL,
            temperature_k REAL NOT NULL,
            landauer_limit_joules REAL NOT NULL,
            entropy_decrease_nats REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_qopen_session ON quantum_open_systems (session_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_qcycle_type ON quantum_heat_cycles (cycle_type)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS quantum_entropy_audits");
        $pdo->exec("DROP TABLE IF EXISTS quantum_heat_cycles");
        $pdo->exec("DROP TABLE IF EXISTS quantum_open_systems");
    }
};
