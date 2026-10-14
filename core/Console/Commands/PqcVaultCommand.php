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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * Class PqcVaultCommand
 *
 * CLI: php netfusion pqc:vault --encrypt="secret payload"
 *      php netfusion pqc:vault --decrypt='{"v":1,...}'
 */
class PqcVaultCommand extends Command
{
    protected string $name        = 'pqc:vault';
    protected string $description = 'Encrypt or decrypt sensitive data using Post-Quantum Envelope Vault';

    public function handle(array $args = []): int
    {
        $encryptText = $this->option($args, 'encrypt', '');
        $decryptText = $this->option($args, 'decrypt', '');

        try {
            $vault = pqc_vault();

            if ($encryptText !== '') {
                $envelope = $vault->encrypt($encryptText);
                echo "\n=== Quantum-Resistant Encrypted Envelope ===\n";
                echo $envelope . "\n";
                return 0;
            }

            if ($decryptText !== '') {
                $decrypted = $vault->decrypt($decryptText);
                echo "\n=== Decrypted Plaintext ===\n";
                echo $decrypted . "\n";
                return 0;
            }

            echo "Usage:\n";
            echo "  php netfusion pqc:vault --encrypt=\"secret data\"\n";
            echo "  php netfusion pqc:vault --decrypt='<envelope_json>'\n";
            return 0;
        } catch (\Throwable $e) {
            $this->error("PQC Vault operation failed: " . $e->getMessage());
            return 1;
        }
    }
}
