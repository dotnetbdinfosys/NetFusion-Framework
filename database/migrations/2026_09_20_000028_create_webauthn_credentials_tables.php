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
    public function up(Connection|PDO $conn): void
    {
        $exec = function (string $sql) use ($conn) {
            if ($conn instanceof Connection) {
                $conn->statement($sql);
            } else {
                $conn->exec($sql);
            }
        };

        // WebAuthn Credentials Table
        $exec("
            CREATE TABLE IF NOT EXISTS netfusion_webauthn_credentials (
                id VARCHAR(255) PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NULL,
                public_key_pem TEXT NOT NULL,
                attestation_type VARCHAR(64) NOT NULL DEFAULT 'none',
                aaguid VARCHAR(64) NULL,
                sign_count INTEGER NOT NULL DEFAULT 0,
                cose_algorithm VARCHAR(32) NULL,
                transports TEXT NULL,
                created_at DATETIME NOT NULL,
                last_used_at DATETIME NULL
            )
        ");

        // Index on user_id
        $exec("CREATE INDEX IF NOT EXISTS idx_webauthn_user_id ON netfusion_webauthn_credentials (user_id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection|PDO $conn): void
    {
        $exec = function (string $sql) use ($conn) {
            if ($conn instanceof Connection) {
                $conn->statement($sql);
            } else {
                $conn->exec($sql);
            }
        };

        $exec("DROP TABLE IF EXISTS netfusion_webauthn_credentials");
    }
};
