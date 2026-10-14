<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Attestation/AttestationQuote.php
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

use NetFusion\Core\Security\Enclave\Enums\EnclaveType;

/**
 * Hardware-signed Attestation Quote certifying enclave authenticity and measurements.
 */
class AttestationQuote
{
    public function __construct(
        private string $quoteId,
        private EnclaveType $enclaveType,
        private PlatformMeasurement $measurement,
        private string $userData,
        private string $signature,
        private string $signingKeyId,
        private int $timestamp = 0,
        private int $expiresAt = 0
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }
        if ($this->expiresAt === 0) {
            $this->expiresAt = $this->timestamp + 3600; // 1 hour validity
        }
    }

    public function getQuoteId(): string
    {
        return $this->quoteId;
    }

    public function getEnclaveType(): EnclaveType
    {
        return $this->enclaveType;
    }

    public function getMeasurement(): PlatformMeasurement
    {
        return $this->measurement;
    }

    public function getUserData(): string
    {
        return $this->userData;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getSigningKeyId(): string
    {
        return $this->signingKeyId;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function isExpired(?int $now = null): bool
    {
        return ($now ?? time()) > $this->expiresAt;
    }

    /**
     * Compute message string that is signed by the hardware root key.
     */
    public function getSignablePayload(): string
    {
        return $this->enclaveType->value
            . '|' . $this->measurement->getDigest()
            . '|' . $this->userData
            . '|' . $this->timestamp
            . '|' . $this->expiresAt;
    }

    public function toArray(): array
    {
        return [
            'quote_id'       => $this->quoteId,
            'enclave_type'   => $this->enclaveType->value,
            'measurement'    => $this->measurement->toArray(),
            'user_data'      => $this->userData,
            'signing_key_id' => $this->signingKeyId,
            'timestamp'      => $this->timestamp,
            'expires_at'     => $this->expiresAt,
        ];
    }
}
