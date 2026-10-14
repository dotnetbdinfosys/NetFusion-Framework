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
use Stringable;

/**
 * Class HybridCiphertext
 *
 * Encapsulates the hybrid exchange payload:
 * Ephemeral classical public key + ML-KEM ciphertext.
 */
class HybridCiphertext implements JsonSerializable, Stringable
{
    public function __construct(
        public readonly string $ephemeralClassicalPublicKey,
        public readonly string $kemCiphertext
    ) {
    }

    public function toBinary(): string
    {
        return pack('N', strlen($this->ephemeralClassicalPublicKey))
            . $this->ephemeralClassicalPublicKey
            . $this->kemCiphertext;
    }

    public function toBase64(): string
    {
        return base64_encode($this->toBinary());
    }

    public static function fromBinary(string $binary): self
    {
        if (strlen($binary) < 4) {
            throw new \InvalidArgumentException("Invalid hybrid ciphertext binary payload");
        }
        $header = unpack('Nlen', substr($binary, 0, 4));
        $len = $header['len'];
        $classicalPk = substr($binary, 4, $len);
        $kemCiphertext = substr($binary, 4 + $len);
        return new self($classicalPk, $kemCiphertext);
    }

    public static function fromBase64(string $base64): self
    {
        $raw = base64_decode($base64, true);
        if ($raw === false) {
            throw new \InvalidArgumentException("Invalid base64 hybrid ciphertext");
        }
        return self::fromBinary($raw);
    }

    public function __toString(): string
    {
        return $this->toBase64();
    }

    public function toArray(): array
    {
        return [
            'ephemeral_classical_pk' => base64_encode($this->ephemeralClassicalPublicKey),
            'kem_ciphertext'         => base64_encode($this->kemCiphertext),
            'combined_base64'        => $this->toBase64(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
