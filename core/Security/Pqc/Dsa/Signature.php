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

namespace NetFusion\Core\Security\Pqc\Dsa;

use JsonSerializable;
use Stringable;

/**
 * Class Signature
 *
 * Encapsulates ML-DSA digital signature payload (challenge seed + response vector z + hints).
 */
class Signature implements JsonSerializable, Stringable
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $algorithm = 'ml-dsa-44'
    ) {
    }

    public function getBytes(): string
    {
        return $this->bytes;
    }

    public function getBase64(): string
    {
        return base64_encode($this->bytes);
    }

    public function getHex(): string
    {
        return bin2hex($this->bytes);
    }

    public function __toString(): string
    {
        return $this->getBase64();
    }

    public static function fromBase64(string $base64, string $algorithm = 'ml-dsa-44'): self
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException("Invalid base64 signature string");
        }
        return new self($decoded, $algorithm);
    }

    public function toArray(): array
    {
        return [
            'algorithm' => $this->algorithm,
            'signature' => $this->getBase64(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
