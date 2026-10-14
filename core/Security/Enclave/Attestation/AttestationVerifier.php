<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Attestation/AttestationVerifier.php
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

namespace NetFusion\Core\Security\Enclave\Attestation;

use NetFusion\Core\Security\Enclave\Enums\AttestationStatus;
use NetFusion\Core\Security\Enclave\Enums\EnclaveType;

/**
 * Remote Attestation Quote Generator & Cryptographic Verifier.
 */
class AttestationVerifier
{
    public function __construct(
        private string $defaultHardwareSecret = 'DotNetBD-Confidential-Hardware-Platform-Root-Key'
    ) {
    }

    /**
     * Generate a signed AttestationQuote for a given platform measurement and nonce.
     */
    public function generateQuote(
        PlatformMeasurement $measurement,
        string $userData,
        EnclaveType $type = EnclaveType::INTEL_SGX,
        ?string $hardwareSecret = null,
        int $ttlSeconds = 3600
    ): AttestationQuote {
        $secret = $hardwareSecret ?? $this->defaultHardwareSecret;
        $now = time();
        $expiresAt = $now + $ttlSeconds;

        $quoteId = 'quote_' . substr(hash('sha256', $measurement->getDigest() . $now), 0, 16);
        $keyId   = 'hw_key_' . substr(hash('sha256', $secret), 0, 8);

        // Compute signable payload
        $payload = $type->value . '|' . $measurement->getDigest() . '|' . $userData . '|' . $now . '|' . $expiresAt;
        $signature = hash_hmac('sha384', $payload, $secret);

        return new AttestationQuote(
            $quoteId,
            $type,
            $measurement,
            $userData,
            $signature,
            $keyId,
            $now,
            $expiresAt
        );
    }

    /**
     * Verify authenticity, integrity, expiration, and measurement policy of a quote.
     *
     * @param string[]|null $trustedMrEnclaves Whitelist of allowed MRENCLAVE hashes
     */
    public function verify(
        AttestationQuote $quote,
        ?string $hardwareSecret = null,
        ?array $trustedMrEnclaves = null,
        ?string $expectedUserData = null,
        ?int $currentTime = null
    ): AttestationStatus {
        $secret = $hardwareSecret ?? $this->defaultHardwareSecret;

        // 1. Expiration check
        if ($quote->isExpired($currentTime)) {
            return AttestationStatus::EXPIRED;
        }

        // 2. Cryptographic signature check
        $expectedSignature = hash_hmac('sha384', $quote->getSignablePayload(), $secret);
        if (!hash_equals($expectedSignature, $quote->getSignature())) {
            return AttestationStatus::TAMPERED;
        }

        // 3. Expected user data / nonce check (replay prevention)
        if ($expectedUserData !== null && !hash_equals($expectedUserData, $quote->getUserData())) {
            return AttestationStatus::TAMPERED;
        }

        // 4. MRENCLAVE Measurement Whitelist check
        if ($trustedMrEnclaves !== null && !empty($trustedMrEnclaves)) {
            $currentMrEnclave = $quote->getMeasurement()->getMrEnclave();
            $matched = false;
            foreach ($trustedMrEnclaves as $trusted) {
                if (hash_equals($trusted, $currentMrEnclave)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return AttestationStatus::UNTRUSTED;
            }
        }

        return AttestationStatus::VERIFIED;
    }
}
