<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Enclave/Sealing/SealedSecret.php
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

/**
 * Hardware-Sealed Secret bound cryptographically to platform measurements.
 */
class SealedSecret
{
    public function __construct(
        private string $ciphertextHex,
        private string $nonceHex,
        private string $tagHex,
        private string $measurementDigest,
        private string $saltHex,
        private int $createdAt = 0
    ) {
        if ($this->createdAt === 0) {
            $this->createdAt = time();
        }
    }

    public function getCiphertextHex(): string
    {
        return $this->ciphertextHex;
    }

    public function getNonceHex(): string
    {
        return $this->nonceHex;
    }

    public function getTagHex(): string
    {
        return $this->tagHex;
    }

    public function getMeasurementDigest(): string
    {
        return $this->measurementDigest;
    }

    public function getSaltHex(): string
    {
        return $this->saltHex;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'ciphertext'         => $this->ciphertextHex,
            'nonce'              => $this->nonceHex,
            'tag'                => $this->tagHex,
            'measurement_digest' => $this->measurementDigest,
            'salt'               => $this->saltHex,
            'created_at'         => $this->createdAt,
        ];
    }

    public function toJson(): string
    {
        return (string)json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new self(
            $data['ciphertext'],
            $data['nonce'],
            $data['tag'],
            $data['measurement_digest'],
            $data['salt'],
            $data['created_at'] ?? time()
        );
    }
}
