<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Sealing/CryptographicSealer.php
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

namespace NetFusion\Core\Security\Enclave\Sealing;

use NetFusion\Core\Security\Enclave\Attestation\PlatformMeasurement;
use NetFusion\Core\Security\Enclave\Exceptions\UnsealFailedException;

/**
 * Enclave Cryptographic Sealing Engine binding secrets to hardware measurements via HKDF + AES-256-GCM.
 */
class CryptographicSealer
{
    public function __construct(
        private string $defaultMasterSeed = 'DotNetBD-Confidential-Hardware-Root-Sealing-Seed'
    ) {
    }

    /**
     * Derive a 256-bit symmetric sealing key cryptographically entangled with the enclave measurement.
     */
    public function deriveSealingKey(
        PlatformMeasurement $measurement,
        ?string $masterSeed = null,
        ?string $saltBinary = null
    ): string {
        $seed = $masterSeed ?? $this->defaultMasterSeed;
        $salt = $saltBinary ?? hash('sha256', 'DotNetBD-Enclave-Static-Salt', true);

        // HKDF derivation using the measurement digest as additional context info
        return hash_hkdf(
            'sha384',
            $seed,
            32,
            'NetFusion-Enclave-Sealing-Key:' . $measurement->getDigest(),
            $salt
        );
    }

    /**
     * Seal plaintext data within enclave memory bound to the platform measurement.
     */
    public function seal(
        string $plaintext,
        PlatformMeasurement $measurement,
        ?string $masterSeed = null
    ): SealedSecret {
        $salt = random_bytes(16);
        $nonce = random_bytes(12);

        $key = $this->deriveSealingKey($measurement, $masterSeed, $salt);
        $aad = $measurement->getDigest();

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt secret in hardware enclave: ' . openssl_error_string());
        }

        return new SealedSecret(
            bin2hex($ciphertext),
            bin2hex($nonce),
            bin2hex($tag),
            $measurement->getDigest(),
            bin2hex($salt)
        );
    }

    /**
     * Unseal ciphertext data only if current hardware measurement matches sealing measurement.
     *
     * @throws UnsealFailedException
     */
    public function unseal(
        SealedSecret $sealed,
        PlatformMeasurement $currentMeasurement,
        ?string $masterSeed = null
    ): string {
        // Enforce exact measurement equality
        if (!hash_equals($sealed->getMeasurementDigest(), $currentMeasurement->getDigest())) {
            throw new UnsealFailedException(
                "Hardware measurement mismatch: The enclave code or PCR state has changed since the secret was sealed."
            );
        }

        $salt = hex2bin($sealed->getSaltHex());
        $nonce = hex2bin($sealed->getNonceHex());
        $tag = hex2bin($sealed->getTagHex());
        $ciphertext = hex2bin($sealed->getCiphertextHex());

        $key = $this->deriveSealingKey($currentMeasurement, $masterSeed, $salt);
        $aad = $currentMeasurement->getDigest();

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new UnsealFailedException(
                "Cryptographic unsealing failed: Authentication tag verification failed or key is invalid."
            );
        }

        return $plaintext;
    }
}
