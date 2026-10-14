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

namespace NetFusion\Core\Security\Pqc\Kem;

use JsonSerializable;

/**
 * Class EncapsulationResult
 *
 * Holds the encapsulated ciphertext (sent to peer) and the derived 32-byte shared secret key.
 */
class EncapsulationResult implements JsonSerializable
{
    public function __construct(
        public readonly string $ciphertext,
        public readonly string $sharedSecret,
        public readonly string $algorithm = 'ml-kem-768'
    ) {
    }

    public function getCiphertext(): string
    {
        return $this->ciphertext;
    }

    public function getSharedSecret(): string
    {
        return $this->sharedSecret;
    }

    public function getCiphertextBase64(): string
    {
        return base64_encode($this->ciphertext);
    }

    public function getSharedSecretHex(): string
    {
        return bin2hex($this->sharedSecret);
    }

    public function toArray(): array
    {
        return [
            'algorithm'     => $this->algorithm,
            'ciphertext'    => $this->getCiphertextBase64(),
            'shared_secret' => $this->getSharedSecretHex(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
