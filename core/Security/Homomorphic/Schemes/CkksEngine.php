<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Schemes/CkksEngine.php
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

namespace NetFusion\Core\Security\Homomorphic\Schemes;

use NetFusion\Core\Security\Homomorphic\Enums\SchemeType;
use NetFusion\Core\Security\Homomorphic\Math\ModMath;
use NetFusion\Core\Security\Homomorphic\Math\PolyMath;

/**
 * CKKS (Cheon-Kim-Kim-Song) Homomorphic Encryption Engine.
 * Supports approximate floating-point vector arithmetic using fixed-point scaling Delta.
 */
class CkksEngine
{
    /**
     * @param int $degree Polynomial ring degree N
     * @param int $modulus Ciphertext modulus q
     * @param float $scale Scaling factor Delta (e.g. 2^10 = 1024 or 2^20)
     */
    public function __construct(
        private int $degree = 16,
        private int $modulus = 1048576, // 2^20
        private float $scale = 1024.0   // 2^10
    ) {
    }

    public function getDegree(): int
    {
        return $this->degree;
    }

    public function getModulus(): int
    {
        return $this->modulus;
    }

    public function getScale(): float
    {
        return $this->scale;
    }

    /**
     * Generate CKKS KeyPair.
     */
    public function generateKeys(): KeyPair
    {
        $s = [];
        for ($i = 0; $i < $this->degree; $i++) {
            $s[$i] = ModMath::mod(mt_rand(-1, 1), $this->modulus);
        }

        $a = PolyMath::sampleUniform($this->degree, $this->modulus);
        $e = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);

        $as = PolyMath::mul($a, $s, $this->degree, $this->modulus);
        $asPlusE = PolyMath::add($as, $e, $this->modulus);
        $p0 = PolyMath::sub(PolyMath::zero($this->degree), $asPlusE, $this->modulus);
        $p1 = $a;

        return new KeyPair($s, [$p0, $p1]);
    }

    /**
     * Encode floating point value or array into Plaintext polynomial using scale Delta: m = round(Delta * z).
     */
    public function encode(float|array $value): Plaintext
    {
        $poly = array_fill(0, $this->degree, 0);
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                if ($i < $this->degree) {
                    $scaled = (int)round((float)$v * $this->scale);
                    $poly[$i] = ModMath::mod($scaled, $this->modulus);
                }
            }
        } else {
            $scaled = (int)round((float)$value * $this->scale);
            $poly[0] = ModMath::mod($scaled, $this->modulus);
        }

        return new Plaintext($poly, $this->scale);
    }

    /**
     * Decode Plaintext polynomial into float or float array: z = m / Delta.
     */
    public function decode(Plaintext $plain, bool $asArray = false): float|array
    {
        $poly = $plain->getPoly();
        $scale = $plain->getScale() ?? $this->scale;

        $decodeCoeff = function (int $c) use ($scale): float {
            // Convert to signed integer centered at 0
            $half = $this->modulus / 2.0;
            $signed = $c > $half ? $c - $this->modulus : $c;
            return round($signed / $scale, 4);
        };

        if ($asArray) {
            return array_map($decodeCoeff, $poly);
        }

        return $decodeCoeff($poly[0] ?? 0);
    }

    /**
     * Encrypt Plaintext m into Ciphertext c = (c0, c1).
     * c0 = p0 * u + e1 + m
     * c1 = p1 * u + e2
     */
    public function encrypt(Plaintext $plain, KeyPair $keyPair): Ciphertext
    {
        [$p0, $p1] = $keyPair->getPublicKey();

        $u = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);
        $e1 = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);
        $e2 = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);

        $m = $plain->getPoly();

        $p0u = PolyMath::mul($p0, $u, $this->degree, $this->modulus);
        $c0 = PolyMath::add(PolyMath::add($p0u, $e1, $this->modulus), $m, $this->modulus);

        $p1u = PolyMath::mul($p1, $u, $this->degree, $this->modulus);
        $c1 = PolyMath::add($p1u, $e2, $this->modulus);

        return new Ciphertext([$c0, $c1], SchemeType::CKKS, scale: $plain->getScale() ?? $this->scale);
    }

    /**
     * Decrypt Ciphertext c into Plaintext m.
     * m = c0 + c1 * s mod q
     */
    public function decrypt(Ciphertext $cipher, KeyPair $keyPair): Plaintext
    {
        $s = $keyPair->getSecretKey();
        $c0 = $cipher->getC0();
        $c1 = $cipher->getC1();

        $c1s = PolyMath::mul($c1, $s, $this->degree, $this->modulus);
        $m = PolyMath::add($c0, $c1s, $this->modulus);

        return new Plaintext($m, $cipher->getScale());
    }

    /**
     * Homomorphic addition of two approximate ciphertexts.
     */
    public function add(Ciphertext $a, Ciphertext $b): Ciphertext
    {
        $c0 = PolyMath::add($a->getC0(), $b->getC0(), $this->modulus);
        $c1 = PolyMath::add($a->getC1(), $b->getC1(), $this->modulus);
        return new Ciphertext([$c0, $c1], SchemeType::CKKS, scale: $a->getScale());
    }

    /**
     * Homomorphic scalar multiplication with floating-point constant.
     */
    public function multiplyPlain(Ciphertext $cipher, float $scalar): Ciphertext
    {
        $scaledInt = (int)round($scalar);
        $c0 = PolyMath::scalarMul($cipher->getC0(), $scaledInt, $this->modulus);
        $c1 = PolyMath::scalarMul($cipher->getC1(), $scaledInt, $this->modulus);
        return new Ciphertext([$c0, $c1], SchemeType::CKKS, scale: $cipher->getScale());
    }
}
