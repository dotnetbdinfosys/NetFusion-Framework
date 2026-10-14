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
 * Class PqcKeyCommand
 *
 * CLI: php netfusion pqc:key [--type=kem|dsa|hybrid] [--alg=ml-kem-768|ml-dsa-44]
 */
class PqcKeyCommand extends Command
{
    protected string $name        = 'pqc:key';
    protected string $description = 'Generate and inspect Post-Quantum Cryptographic keypairs';

    public function handle(array $args = []): int
    {
        $type = strtolower($this->option($args, 'type', 'hybrid'));
        $alg = $this->option($args, 'alg', '');

        try {
            $pqc = pqc();

            if ($type === 'kem') {
                $kem = $pqc->kem($alg ?: null);
                $kp = $kem->generateKeyPair();
                echo "\n=== ML-KEM KeyPair ({$kem->getParameters()->name}) ===\n";
                echo "Public Key (Base64):\n" . $kp->getPublicKeyBase64() . "\n\n";
                echo "Secret Key (Base64):\n" . substr($kp->getSecretKeyBase64(), 0, 64) . "... (truncated)\n";
                return 0;
            }

            if ($type === 'dsa') {
                $dsa = $pqc->dsa($alg ?: null);
                $kp = $dsa->generateKeyPair();
                echo "\n=== ML-DSA KeyPair ({$dsa->getParameters()->name}) ===\n";
                echo "Public Key (Base64):\n" . $kp->getPublicKeyBase64() . "\n\n";
                echo "Secret Key (Base64):\n" . substr($kp->getSecretKeyBase64(), 0, 64) . "... (truncated)\n";
                return 0;
            }

            // Default: Hybrid (X25519 + ML-KEM-768)
            $kp = $pqc->hybrid()->generateKeyPair();
            echo "\n=== Hybrid KeyPair (X25519 + ML-KEM-768) ===\n";
            echo "Composite Public Key (Base64):\n" . $kp->getCompositePublicKeyBase64() . "\n\n";
            echo "Classical Public Key (Base64): " . base64_encode($kp->getClassicalPublicKey()) . "\n";
            echo "PQC Public Key (Base64):       " . substr($kp->getPqcKeyPair()->getPublicKeyBase64(), 0, 48) . "...\n";
            return 0;
        } catch (\Throwable $e) {
            $this->error("PQC key generation failed: " . $e->getMessage());
            return 1;
        }
    }
}
