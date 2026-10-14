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

namespace NetFusion\Core\Security\Pqc;

use NetFusion\Core\Security\Pqc\Kem\MlKemEngine;
use NetFusion\Core\Security\Pqc\Kem\MlKemParameters;
use NetFusion\Core\Security\Pqc\Dsa\MlDsaEngine;
use NetFusion\Core\Security\Pqc\Dsa\MlDsaParameters;
use NetFusion\Core\Security\Pqc\Dsa\Signature;
use NetFusion\Core\Security\Pqc\Hybrid\HybridKeyExchange;
use NetFusion\Core\Security\Pqc\Vault\PqcEnvelopeVault;

/**
 * Class PqcManager
 *
 * Central Facade coordinating Post-Quantum Cryptography:
 * - Lattice Key Encapsulation (ML-KEM-512 / 768 / 1024)
 * - Lattice Digital Signatures (ML-DSA-44 / 65 / 87)
 * - Hybrid Post-Quantum Key Agreement (X25519 + ML-KEM-768)
 * - Post-Quantum Envelope Encryption Vault
 */
class PqcManager
{
    protected MlKemEngine $kemEngine;
    protected MlDsaEngine $dsaEngine;
    protected HybridKeyExchange $hybridExchange;
    protected PqcEnvelopeVault $vault;

    public function __construct(
        ?MlKemEngine $kemEngine = null,
        ?MlDsaEngine $dsaEngine = null,
        ?HybridKeyExchange $hybridExchange = null,
        ?PqcEnvelopeVault $vault = null
    ) {
        $this->kemEngine = $kemEngine ?? new MlKemEngine(MlKemParameters::mlKem768());
        $this->dsaEngine = $dsaEngine ?? new MlDsaEngine(MlDsaParameters::mlDsa44());
        $this->hybridExchange = $hybridExchange ?? new HybridKeyExchange($this->kemEngine);
        $this->vault = $vault ?? new PqcEnvelopeVault($this->hybridExchange);
    }

    public function kem(?string $parameterSet = null): MlKemEngine
    {
        if ($parameterSet !== null) {
            return new MlKemEngine(MlKemParameters::fromName($parameterSet));
        }
        return $this->kemEngine;
    }

    public function dsa(?string $parameterSet = null): MlDsaEngine
    {
        if ($parameterSet !== null) {
            return new MlDsaEngine(MlDsaParameters::fromName($parameterSet));
        }
        return $this->dsaEngine;
    }

    public function hybrid(): HybridKeyExchange
    {
        return $this->hybridExchange;
    }

    public function vault(): PqcEnvelopeVault
    {
        return $this->vault;
    }

    /**
     * Sign a message with ML-DSA.
     */
    public function sign(string $message, string $secretKey, ?string $param = null): Signature
    {
        return $this->dsa($param)->sign($message, $secretKey);
    }

    /**
     * Verify an ML-DSA digital signature.
     */
    public function verify(string $message, Signature|string $signature, string $publicKey, ?string $param = null): bool
    {
        return $this->dsa($param)->verify($message, $signature, $publicKey);
    }

    /**
     * Encrypt data with Quantum-Resistant Envelope Vault.
     */
    public function encrypt(string $data, ?string $keyId = null, string $aad = ''): string
    {
        return $this->vault->encrypt($data, $keyId, $aad);
    }

    /**
     * Decrypt data from Quantum-Resistant Envelope Vault.
     */
    public function decrypt(string $envelopeJson, string $aad = ''): string
    {
        return $this->vault->decrypt($envelopeJson, null, $aad);
    }

    /**
     * Run a self-benchmark measuring performance of PQC operations.
     *
     * @return array<string, mixed>
     */
    public function benchmark(): array
    {
        // 1. ML-KEM-768
        $t0 = microtime(true);
        $kemPair = $this->kemEngine->generateKeyPair();
        $tKemKeyGen = (microtime(true) - $t0) * 1000;

        $t0 = microtime(true);
        $encap = $this->kemEngine->encapsulate($kemPair->getPublicKey());
        $tKemEncap = (microtime(true) - $t0) * 1000;

        $t0 = microtime(true);
        $this->kemEngine->decapsulate($kemPair->getSecretKey(), $encap->getCiphertext());
        $tKemDecap = (microtime(true) - $t0) * 1000;

        // 2. ML-DSA-44
        $t0 = microtime(true);
        $dsaPair = $this->dsaEngine->generateKeyPair();
        $tDsaKeyGen = (microtime(true) - $t0) * 1000;

        $msg = "NetFusion Benchmark Test Payload";
        $t0 = microtime(true);
        $sig = $this->dsaEngine->sign($msg, $dsaPair->getSecretKey());
        $tDsaSign = (microtime(true) - $t0) * 1000;

        $t0 = microtime(true);
        $this->dsaEngine->verify($msg, $sig, $dsaPair->getPublicKey());
        $tDsaVerify = (microtime(true) - $t0) * 1000;

        // 3. Hybrid Key Exchange
        $t0 = microtime(true);
        $hybPair = $this->hybridExchange->generateKeyPair();
        $tHybKeyGen = (microtime(true) - $t0) * 1000;

        $t0 = microtime(true);
        $hybEncap = $this->hybridExchange->encapsulate($hybPair);
        $tHybEncap = (microtime(true) - $t0) * 1000;

        $t0 = microtime(true);
        $this->hybridExchange->decapsulate($hybPair, $hybEncap['ciphertext']);
        $tHybDecap = (microtime(true) - $t0) * 1000;

        return [
            'kem' => [
                'algorithm'   => $this->kemEngine->getParameters()->name,
                'keygen_ms'   => round($tKemKeyGen, 2),
                'encap_ms'    => round($tKemEncap, 2),
                'decap_ms'    => round($tKemDecap, 2),
                'pk_bytes'    => strlen($kemPair->getPublicKey()),
                'sk_bytes'    => strlen($kemPair->getSecretKey()),
                'ct_bytes'    => strlen($encap->getCiphertext()),
            ],
            'dsa' => [
                'algorithm'   => $this->dsaEngine->getParameters()->name,
                'keygen_ms'   => round($tDsaKeyGen, 2),
                'sign_ms'     => round($tDsaSign, 2),
                'verify_ms'   => round($tDsaVerify, 2),
                'sig_bytes'   => strlen($sig->getBytes()),
            ],
            'hybrid' => [
                'algorithm'   => 'X25519 + ML-KEM-768',
                'keygen_ms'   => round($tHybKeyGen, 2),
                'encap_ms'    => round($tHybEncap, 2),
                'decap_ms'    => round($tHybDecap, 2),
            ],
        ];
    }
}
