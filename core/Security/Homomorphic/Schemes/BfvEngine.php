<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Schemes/BfvEngine.php
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
 * BFV (Brakerski-Fan-Vercauteren) Homomorphic Encryption Engine.
 * Supports exact integer arithmetic over ring R_q = Z_q[X] / (X^N + 1) with plaintext modulus t.
 */
class BfvEngine
{
    /**
     * @param int $degree Polynomial degree N (power of 2, e.g. 16, 64, 128)
     * @param int $modulus Ciphertext modulus q
     * @param int $plainModulus Plaintext modulus t (t < q)
     */
    public function __construct(
        private int $degree = 16,
        private int $modulus = 65537,
        private int $plainModulus = 17
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

    public function getPlainModulus(): int
    {
        return $this->plainModulus;
    }

    /**
     * Generate BFV KeyPair: Secret Key s, Public Key pk = (p0, p1).
     */
    public function generateKeys(): KeyPair
    {
        // Sample secret key s with ternary coefficients {-1, 0, 1}
        $s = [];
        for ($i = 0; $i < $this->degree; $i++) {
            $s[$i] = ModMath::mod(mt_rand(-1, 1), $this->modulus);
        }

        // Sample uniform polynomial a in R_q
        $a = PolyMath::sampleUniform($this->degree, $this->modulus);

        // Sample small noise e
        $e = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);

        // p0 = -(a * s + e) mod q
        $as = PolyMath::mul($a, $s, $this->degree, $this->modulus);
        $asPlusE = PolyMath::add($as, $e, $this->modulus);
        $p0 = PolyMath::sub(PolyMath::zero($this->degree), $asPlusE, $this->modulus);

        // p1 = a
        $p1 = $a;

        return new KeyPair($s, [$p0, $p1]);
    }

    /**
     * Encode integer value or array into Plaintext polynomial.
     */
    public function encode(int|array $value): Plaintext
    {
        $poly = array_fill(0, $this->degree, 0);
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                if ($i < $this->degree) {
                    $poly[$i] = ModMath::mod($v, $this->plainModulus);
                }
            }
        } else {
            $poly[0] = ModMath::mod($value, $this->plainModulus);
        }
        return new Plaintext($poly);
    }

    /**
     * Decode Plaintext polynomial into integer value or array.
     */
    public function decode(Plaintext $plain, bool $asArray = false): int|array
    {
        $poly = $plain->getPoly();
        if ($asArray) {
            return $poly;
        }
        return $poly[0] ?? 0;
    }

    /**
     * Encrypt Plaintext m into Ciphertext c = (c0, c1).
     * Delta = floor(q / t).
     * c0 = p0 * u + e1 + Delta * m
     * c1 = p1 * u + e2
     */
    public function encrypt(Plaintext $plain, KeyPair $keyPair): Ciphertext
    {
        [$p0, $p1] = $keyPair->getPublicKey();
        $delta = (int)floor($this->modulus / $this->plainModulus);

        $u = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);
        $e1 = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);
        $e2 = PolyMath::sampleNoise($this->degree, $this->modulus, bound: 1);

        // Delta * m
        $scaledM = PolyMath::scalarMul($plain->getPoly(), $delta, $this->modulus);

        // c0 = p0 * u + e1 + Delta * m
        $p0u = PolyMath::mul($p0, $u, $this->degree, $this->modulus);
        $c0 = PolyMath::add(PolyMath::add($p0u, $e1, $this->modulus), $scaledM, $this->modulus);

        // c1 = p1 * u + e2
        $p1u = PolyMath::mul($p1, $u, $this->degree, $this->modulus);
        $c1 = PolyMath::add($p1u, $e2, $this->modulus);

        return new Ciphertext([$c0, $c1], SchemeType::BFV, noiseEstimate: 1);
    }

    /**
     * Decrypt Ciphertext c into Plaintext m.
     * m = round( (t / q) * (c0 + c1 * s) ) mod t
     */
    public function decrypt(Ciphertext $cipher, KeyPair $keyPair): Plaintext
    {
        $s = $keyPair->getSecretKey();
        $c0 = $cipher->getC0();
        $c1 = $cipher->getC1();

        // c0 + c1 * s
        $c1s = PolyMath::mul($c1, $s, $this->degree, $this->modulus);
        $noisyM = PolyMath::add($c0, $c1s, $this->modulus);

        // Decode each coefficient: round((t * x) / q) mod t
        $m = [];
        $t = $this->plainModulus;
        $q = $this->modulus;
        $half = $q / 2;

        foreach ($noisyM as $coeff) {
            $signed = $coeff > $half ? $coeff - $q : $coeff;
            $val = (int)round(($t * $signed) / $q);
            $m[] = ModMath::mod($val, $t);
        }

        return new Plaintext($m);
    }

    /**
     * Homomorphic Addition: c_add = (c0_a + c0_b, c1_a + c1_b).
     */
    public function add(Ciphertext $a, Ciphertext $b): Ciphertext
    {
        $c0 = PolyMath::add($a->getC0(), $b->getC0(), $this->modulus);
        $c1 = PolyMath::add($a->getC1(), $b->getC1(), $this->modulus);
        return new Ciphertext([$c0, $c1], SchemeType::BFV, noiseEstimate: $a->getNoiseEstimate() + $b->getNoiseEstimate());
    }

    /**
     * Homomorphic Subtraction: c_sub = (c0_a - c0_b, c1_a - c1_b).
     */
    public function sub(Ciphertext $a, Ciphertext $b): Ciphertext
    {
        $c0 = PolyMath::sub($a->getC0(), $b->getC0(), $this->modulus);
        $c1 = PolyMath::sub($a->getC1(), $b->getC1(), $this->modulus);
        return new Ciphertext([$c0, $c1], SchemeType::BFV, noiseEstimate: $a->getNoiseEstimate() + $b->getNoiseEstimate());
    }

    /**
     * Homomorphic Scalar Multiplication with Plaintext: c_mul = (c0 * plain, c1 * plain).
     */
    public function multiplyPlain(Ciphertext $cipher, int $scalar): Ciphertext
    {
        $sMod = ModMath::mod($scalar, $this->plainModulus);
        $c0 = PolyMath::scalarMul($cipher->getC0(), $sMod, $this->modulus);
        $c1 = PolyMath::scalarMul($cipher->getC1(), $sMod, $this->modulus);
        return new Ciphertext([$c0, $c1], SchemeType::BFV, noiseEstimate: $cipher->getNoiseEstimate() * abs($scalar));
    }
}
