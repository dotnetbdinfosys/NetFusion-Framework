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
 * Class PqcBenchmarkCommand
 *
 * CLI: php netfusion pqc:benchmark [--format=text|json]
 */
class PqcBenchmarkCommand extends Command
{
    protected string $name        = 'pqc:benchmark';
    protected string $description = 'Benchmark Post-Quantum Cryptography performance (ML-KEM, ML-DSA, Hybrid Exchange)';

    public function handle(array $args = []): int
    {
        $format = $this->option($args, 'format', 'text');

        try {
            $pqc = pqc();
            echo "Running Post-Quantum Cryptographic benchmarks...\n";
            $results = $pqc->benchmark();
        } catch (\Throwable $e) {
            $this->error("PQC benchmark failed: " . $e->getMessage());
            return 1;
        }

        if ($format === 'json') {
            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            return 0;
        }

        echo "\n=== NetFusion Post-Quantum Cryptography (PQC) Benchmark ===\n";
        echo "===========================================================\n";

        // KEM
        $kem = $results['kem'];
        echo "1. Key Encapsulation (ML-KEM-768):\n";
        echo sprintf("   KeyGen: %8.2f ms | Public Key: %d bytes | Secret Key: %d bytes\n", $kem['keygen_ms'], $kem['pk_bytes'], $kem['sk_bytes']);
        echo sprintf("   Encap:  %8.2f ms | Ciphertext: %d bytes\n", $kem['encap_ms'], $kem['ct_bytes']);
        echo sprintf("   Decap:  %8.2f ms\n", $kem['decap_ms']);

        // DSA
        $dsa = $results['dsa'];
        echo "\n2. Digital Signatures (ML-DSA-44):\n";
        echo sprintf("   KeyGen: %8.2f ms\n", $dsa['keygen_ms']);
        echo sprintf("   Sign:   %8.2f ms | Signature:  %d bytes\n", $dsa['sign_ms'], $dsa['sig_bytes']);
        echo sprintf("   Verify: %8.2f ms\n", $dsa['verify_ms']);

        // Hybrid
        $hyb = $results['hybrid'];
        echo "\n3. Hybrid Key Agreement (X25519 + ML-KEM-768):\n";
        echo sprintf("   KeyGen: %8.2f ms\n", $hyb['keygen_ms']);
        echo sprintf("   Encap:  %8.2f ms\n", $hyb['encap_ms']);
        echo sprintf("   Decap:  %8.2f ms\n", $hyb['decap_ms']);

        echo "\nStatus: OK — All PQC Primitives Operational.\n";
        return 0;
    }
}
