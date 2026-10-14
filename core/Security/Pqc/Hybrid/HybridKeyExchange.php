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

namespace NetFusion\Core\Security\Pqc\Hybrid;

use NetFusion\Core\Security\Pqc\Kem\MlKemEngine;
use NetFusion\Core\Security\Pqc\Kem\MlKemParameters;
use RuntimeException;

/**
 * Class HybridKeyExchange
 *
 * Implements IETF / NIST draft Hybrid Post-Quantum Key Exchange:
 * Combines classical X25519 (ECDH) + Post-Quantum ML-KEM-768 with dual-layer HKDF-SHA256.
 */
class HybridKeyExchange
{
    protected MlKemEngine $kemEngine;

    public function __construct(?MlKemEngine $kemEngine = null)
    {
        $this->kemEngine = $kemEngine ?? new MlKemEngine(MlKemParameters::mlKem768());
    }

    public function getKemEngine(): MlKemEngine
    {
        return $this->kemEngine;
    }

    /**
     * Generate a new Hybrid KeyPair (X25519 + ML-KEM-768).
     *
     * @return HybridKeyPair
     */
    public function generateKeyPair(): HybridKeyPair
    {
        if (function_exists('sodium_crypto_box_keypair')) {
            $classicalPair = sodium_crypto_box_keypair();
            $classicalPk = sodium_crypto_box_publickey($classicalPair);
            $classicalSk = sodium_crypto_box_secretkey($classicalPair);
        } else {
            // Fallback: 32 random bytes simulation
            $classicalSk = random_bytes(32);
            $classicalPk = hash('sha256', $classicalSk . 'CLASSICAL_PUB', true);
        }

        $pqcKeyPair = $this->kemEngine->generateKeyPair();

        return new HybridKeyPair($classicalPk, $classicalSk, $pqcKeyPair);
    }

    /**
     * Parse composite public key into classical and PQC components.
     *
     * @param string $compositeKey Raw binary or base64 string
     * @return array{classical: string, pqc: string}
     */
    public function parseCompositePublicKey(string $compositeKey): array
    {
        $raw = base64_decode($compositeKey, true);
        if ($raw !== false && strlen($raw) > 100) {
            $compositeKey = $raw;
        }

        if (strlen($compositeKey) < 4) {
            throw new RuntimeException("Invalid composite public key");
        }

        $lenHeader = unpack('Nlen', substr($compositeKey, 0, 4));
        $classicalLen = $lenHeader['len'];
        $classicalPk = substr($compositeKey, 4, $classicalLen);
        $pqcPk = substr($compositeKey, 4 + $classicalLen);

        return [
            'classical' => $classicalPk,
            'pqc'       => $pqcPk,
        ];
    }

    /**
     * Encapsulate to recipient's hybrid public key.
     *
     * @param HybridKeyPair|string $recipientKey Either HybridKeyPair or composite public key
     * @return array{ciphertext: HybridCiphertext, session: HybridSession}
     */
    public function encapsulate(HybridKeyPair|string $recipientKey): array
    {
        if ($recipientKey instanceof HybridKeyPair) {
            $classicalPk = $recipientKey->getClassicalPublicKey();
            $pqcPk = $recipientKey->getPqcPublicKey();
        } else {
            $parsed = $this->parseCompositePublicKey($recipientKey);
            $classicalPk = $parsed['classical'];
            $pqcPk = $parsed['pqc'];
        }

        // 1. Classical Ephemeral Key Exchange (X25519)
        if (function_exists('sodium_crypto_box_keypair')) {
            $ephemeralPair = sodium_crypto_box_keypair();
            $ephemeralPk = sodium_crypto_box_publickey($ephemeralPair);
            $ephemeralSk = sodium_crypto_box_secretkey($ephemeralPair);

            // Compute classical shared secret = scalarmult(ephemeralSk, recipientPk)
            $classicalSecret = sodium_crypto_scalarmult($ephemeralSk, $classicalPk);
        } else {
            $ephemeralSk = random_bytes(32);
            $ephemeralPk = hash('sha256', $ephemeralSk . 'CLASSICAL_PUB', true);
            $classicalSecret = hash('sha256', $ephemeralSk . $classicalPk, true);
        }

        // 2. Post-Quantum Key Encapsulation (ML-KEM)
        $kemResult = $this->kemEngine->encapsulate($pqcPk);
        $pqcSecret = $kemResult->getSharedSecret();
        $kemCiphertext = $kemResult->getCiphertext();

        // 3. Dual-KDF Master Key Derivation
        $masterKey = $this->combineSecrets($classicalSecret, $pqcSecret, $ephemeralPk, $kemCiphertext);
        $sessionId = bin2hex(hash('sha256', $ephemeralPk . $kemCiphertext, true));

        $ciphertext = new HybridCiphertext($ephemeralPk, $kemCiphertext);
        $session = new HybridSession($masterKey, $sessionId);

        return [
            'ciphertext' => $ciphertext,
            'session'    => $session,
        ];
    }

    /**
     * Decapsulate hybrid ciphertext using recipient's hybrid keypair.
     *
     * @param HybridKeyPair $recipientKeyPair
     * @param HybridCiphertext|string $ciphertext
     * @return HybridSession
     */
    public function decapsulate(HybridKeyPair $recipientKeyPair, HybridCiphertext|string $ciphertext): HybridSession
    {
        if (is_string($ciphertext)) {
            $ciphertext = HybridCiphertext::fromBase64($ciphertext);
        }

        $ephemeralPk = $ciphertext->ephemeralClassicalPublicKey;
        $kemCiphertext = $ciphertext->kemCiphertext;

        // 1. Classical ECDH decapsulation
        if (function_exists('sodium_crypto_scalarmult')) {
            $classicalSecret = sodium_crypto_scalarmult($recipientKeyPair->getClassicalSecretKey(), $ephemeralPk);
        } else {
            $classicalSecret = hash('sha256', $recipientKeyPair->getClassicalSecretKey() . $ephemeralPk, true);
        }

        // 2. Post-Quantum decapsulation
        $pqcSecret = $this->kemEngine->decapsulate(
            $recipientKeyPair->getPqcSecretKey(),
            $kemCiphertext
        );

        // 3. Dual-KDF Master Key Derivation
        $masterKey = $this->combineSecrets($classicalSecret, $pqcSecret, $ephemeralPk, $kemCiphertext);
        $sessionId = bin2hex(hash('sha256', $ephemeralPk . $kemCiphertext, true));

        return new HybridSession($masterKey, $sessionId);
    }

    /**
     * Dual-KDF Combiner: HKDF-SHA256(IKM = classical_ss || pqc_ss, Info = context).
     */
    protected function combineSecrets(string $classical, string $pqc, string $ephemeralPk, string $kemCiphertext): string
    {
        $ikm = $classical . $pqc;
        $salt = hash('sha256', 'NETFUSION_PQC_HYBRID_SALT_V1', true);
        $info = 'HYBRID_X25519_MLKEM768_' . hash('sha256', $ephemeralPk . $kemCiphertext, true);

        return hash_hkdf('sha256', $ikm, 32, $info, $salt);
    }
}
