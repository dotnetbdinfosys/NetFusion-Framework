<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Math/PolyMath.php
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

namespace NetFusion\Core\Security\Homomorphic\Math;

/**
 * Polynomial Ring Arithmetic over R_q = Z_q[X] / (X^N + 1)
 * Used in BFV, BGV, and CKKS Homomorphic Encryption schemes.
 */
class PolyMath
{
    /**
     * Create zero polynomial of degree N.
     *
     * @return int[]
     */
    public static function zero(int $degree): array
    {
        return array_fill(0, $degree, 0);
    }

    /**
     * Add two polynomials modulo q: (a + b) mod q.
     */
    public static function add(array $a, array $b, int $modulus): array
    {
        $n = max(count($a), count($b));
        $res = [];
        for ($i = 0; $i < $n; $i++) {
            $va = $a[$i] ?? 0;
            $vb = $b[$i] ?? 0;
            $res[$i] = ModMath::add($va, $vb, $modulus);
        }
        return $res;
    }

    /**
     * Subtract two polynomials modulo q: (a - b) mod q.
     */
    public static function sub(array $a, array $b, int $modulus): array
    {
        $n = max(count($a), count($b));
        $res = [];
        for ($i = 0; $i < $n; $i++) {
            $va = $a[$i] ?? 0;
            $vb = $b[$i] ?? 0;
            $res[$i] = ModMath::sub($va, $vb, $modulus);
        }
        return $res;
    }

    /**
     * Multiply polynomial by integer scalar modulo q: (c * a) mod q.
     */
    public static function scalarMul(array $a, int $c, int $modulus): array
    {
        $res = [];
        foreach ($a as $i => $val) {
            $res[$i] = ModMath::mul($val, $c, $modulus);
        }
        return $res;
    }

    /**
     * Multiply two polynomials in the cyclotomic ring R_q = Z_q[X] / (X^N + 1).
     * Where X^N = -1 mod (X^N + 1).
     *
     * @param int[] $a
     * @param int[] $b
     * @param int $degree N (e.g. 16, 64, 128, 256)
     * @param int $modulus q
     * @return int[]
     */
    public static function mul(array $a, array $b, int $degree, int $modulus): array
    {
        $res = array_fill(0, $degree, 0);

        for ($i = 0; $i < $degree; $i++) {
            $ai = $a[$i] ?? 0;
            if ($ai === 0) {
                continue;
            }
            for ($j = 0; $j < $degree; $j++) {
                $bj = $b[$j] ?? 0;
                if ($bj === 0) {
                    continue;
                }

                $prod = ModMath::mul($ai, $bj, $modulus);
                $deg = $i + $j;

                if ($deg < $degree) {
                    // Normal term: + (ai * bj) * X^(i+j)
                    $res[$deg] = ModMath::add($res[$deg], $prod, $modulus);
                } else {
                    // Wraparound by X^N = -1: - (ai * bj) * X^(i+j - N)
                    $remDeg = $deg - $degree;
                    $res[$remDeg] = ModMath::sub($res[$remDeg], $prod, $modulus);
                }
            }
        }

        return $res;
    }

    /**
     * Generate discrete Gaussian / bounded uniform noise polynomial.
     */
    public static function sampleNoise(int $degree, int $modulus, int $bound = 2): array
    {
        $res = [];
        for ($i = 0; $i < $degree; $i++) {
            $e = mt_rand(-$bound, $bound);
            $res[$i] = ModMath::mod($e, $modulus);
        }
        return $res;
    }

    /**
     * Sample uniform random polynomial in Z_q.
     */
    public static function sampleUniform(int $degree, int $modulus): array
    {
        $res = [];
        for ($i = 0; $i < $degree; $i++) {
            $res[$i] = mt_rand(0, $modulus - 1);
        }
        return $res;
    }
}
