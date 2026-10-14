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

use JsonSerializable;
use NetFusion\Core\Security\Pqc\Kem\KemKeyPair;

/**
 * Class HybridKeyPair
 *
 * Holds classical (X25519) and post-quantum (ML-KEM-768) dual keypairs.
 */
class HybridKeyPair implements JsonSerializable
{
    public function __construct(
        public readonly string $classicalPublicKey,
        public readonly string $classicalSecretKey,
        public readonly KemKeyPair $pqcKeyPair
    ) {
    }

    public function getClassicalPublicKey(): string
    {
        return $this->classicalPublicKey;
    }

    public function getClassicalSecretKey(): string
    {
        return $this->classicalSecretKey;
    }

    public function getPqcKeyPair(): KemKeyPair
    {
        return $this->pqcKeyPair;
    }

    public function getPqcPublicKey(): string
    {
        return $this->pqcKeyPair->getPublicKey();
    }

    public function getPqcSecretKey(): string
    {
        return $this->pqcKeyPair->getSecretKey();
    }

    /**
     * Export composite hybrid public key (32 bytes classical + ML-KEM pk).
     */
    public function getCompositePublicKey(): string
    {
        return pack('N', strlen($this->classicalPublicKey))
            . $this->classicalPublicKey
            . $this->getPqcPublicKey();
    }

    public function getCompositePublicKeyBase64(): string
    {
        return base64_encode($this->getCompositePublicKey());
    }

    public function toArray(): array
    {
        return [
            'type'                   => 'HYBRID_X25519_MLKEM768',
            'classical_public_key'   => base64_encode($this->classicalPublicKey),
            'classical_secret_key'   => base64_encode($this->classicalSecretKey),
            'pqc_public_key'         => $this->pqcKeyPair->getPublicKeyBase64(),
            'pqc_secret_key'         => $this->pqcKeyPair->getSecretKeyBase64(),
            'composite_public_key'   => $this->getCompositePublicKeyBase64(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
