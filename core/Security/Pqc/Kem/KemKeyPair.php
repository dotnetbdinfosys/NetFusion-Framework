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
 * Class KemKeyPair
 *
 * Holds public and private keys for ML-KEM Key Encapsulation.
 */
class KemKeyPair implements JsonSerializable
{
    public function __construct(
        public readonly string $publicKey,
        public readonly string $secretKey,
        public readonly MlKemParameters $parameters
    ) {
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getPublicKeyBase64(): string
    {
        return base64_encode($this->publicKey);
    }

    public function getSecretKeyBase64(): string
    {
        return base64_encode($this->secretKey);
    }

    public function getParameters(): MlKemParameters
    {
        return $this->parameters;
    }

    public function toArray(): array
    {
        return [
            'algorithm'  => $this->parameters->name,
            'public_key' => $this->getPublicKeyBase64(),
            'secret_key' => $this->getSecretKeyBase64(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
