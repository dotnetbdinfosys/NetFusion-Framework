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

namespace NetFusion\Core\Security\Pqc\Math;

/**
 * Class PolynomialRing
 *
 * Implements polynomial arithmetic over the ring R_q = Z_q[X] / (X^n + 1)
 * used in NIST FIPS 203 (ML-KEM / Crystals-Kyber).
 * Modulus q = 3329, Degree n = 256.
 */
class PolynomialRing
{
    public const MODULUS = 3329;
    public const DEGREE = 256;
    public const ROOT_OF_UNITY = 17; // 17^128 = -1 mod 3329, 17^256 = 1 mod 3329

    /**
     * Return a zero polynomial of degree n.
     *
     * @return int[]
     */
    public static function zero(): array
    {
        return array_fill(0, self::DEGREE, 0);
    }

    /**
     * Add two polynomials modulo q.
     *
     * @param int[] $a
     * @param int[] $b
     * @return int[]
     */
    public static function add(array $a, array $b): array
    {
        $res = [];
        $q = self::MODULUS;
        for ($i = 0; $i < self::DEGREE; $i++) {
            $res[$i] = ($a[$i] + $b[$i]) % $q;
        }
        return $res;
    }

    /**
     * Subtract polynomial b from a modulo q.
     *
     * @param int[] $a
     * @param int[] $b
     * @return int[]
     */
    public static function sub(array $a, array $b): array
    {
        $res = [];
        $q = self::MODULUS;
        for ($i = 0; $i < self::DEGREE; $i++) {
            $diff = ($a[$i] - $b[$i]) % $q;
            $res[$i] = $diff < 0 ? $diff + $q : $diff;
        }
        return $res;
    }

    /**
     * Scalar multiply a polynomial by an integer modulo q.
     *
     * @param int $scalar
     * @param int[] $a
     * @return int[]
     */
    public static function scalarMul(int $scalar, array $a): array
    {
        $res = [];
        $q = self::MODULUS;
        $scalar = (($scalar % $q) + $q) % $q;
        for ($i = 0; $i < self::DEGREE; $i++) {
            $res[$i] = ($scalar * $a[$i]) % $q;
        }
        return $res;
    }

    /**
     * Multiply two polynomials in R_q = Z_q[X] / (X^256 + 1).
     * Since X^256 = -1 mod (X^256 + 1), higher degree terms wrap around with sign negation.
     *
     * @param int[] $a
     * @param int[] $b
     * @return int[]
     */
    public static function multiply(array $a, array $b): array
    {
        $n = self::DEGREE;
        $q = self::MODULUS;
        $res = array_fill(0, $n, 0);

        for ($i = 0; $i < $n; $i++) {
            $ai = $a[$i];
            if ($ai === 0) {
                continue;
            }
            for ($j = 0; $j < $n; $j++) {
                $bj = $b[$j];
                if ($bj === 0) {
                    continue;
                }
                $prod = ($ai * $bj) % $q;
                $idx = $i + $j;
                if ($idx < $n) {
                    $res[$idx] = ($res[$idx] + $prod) % $q;
                } else {
                    $wIdx = $idx - $n;
                    $diff = ($res[$wIdx] - $prod) % $q;
                    $res[$wIdx] = $diff < 0 ? $diff + $q : $diff;
                }
            }
        }

        return $res;
    }

    /**
     * Number Theoretic Transform (NTT) for fast polynomial operations.
     *
     * @param int[] $a
     * @return int[]
     */
    public static function ntt(array $a): array
    {
        $n = self::DEGREE;
        $q = self::MODULUS;
        $res = $a;

        $len = 128;
        $k = 1;
        while ($len >= 2) {
            $start = 0;
            while ($start < $n) {
                $zeta = self::modPow(self::ROOT_OF_UNITY, self::bitRev7($k), $q);
                $k++;
                for ($j = $start; $j < $start + $len; $j++) {
                    $t = ($zeta * $res[$j + $len]) % $q;
                    $res[$j + $len] = ($res[$j] - $t + $q) % $q;
                    $res[$j] = ($res[$j] + $t) % $q;
                }
                $start += 2 * $len;
            }
            $len >>= 1;
        }

        return $res;
    }

    /**
     * Inverse NTT.
     *
     * @param int[] $a
     * @return int[]
     */
    public static function invNtt(array $a): array
    {
        $n = self::DEGREE;
        $q = self::MODULUS;
        $res = $a;

        $len = 2;
        $k = 127;
        while ($len <= 128) {
            $start = 0;
            while ($start < $n) {
                $zeta = self::modPow(self::ROOT_OF_UNITY, self::bitRev7($k), $q);
                $zeta = ($q - $zeta) % $q; // negate
                $k--;
                for ($j = $start; $j < $start + $len; $j++) {
                    $t = $res[$j];
                    $res[$j] = ($t + $res[$j + $len]) % $q;
                    $diff = ($t - $res[$j + $len] + $q) % $q;
                    $res[$j + $len] = ($zeta * $diff) % $q;
                }
                $start += 2 * $len;
            }
            $len <<= 1;
        }

        // Multiply by n^-1 mod q (256^-1 mod 3329 = 3316)
        $nInv = self::modInverse($n, $q);
        for ($i = 0; $i < $n; $i++) {
            $res[$i] = ($res[$i] * $nInv) % $q;
        }

        return $res;
    }

    /**
     * Compress coefficient to d bits: round(x * 2^d / q) mod 2^d.
     *
     * @param int[] $a
     * @param int $d
     * @return int[]
     */
    public static function compress(array $a, int $d): array
    {
        $q = self::MODULUS;
        $twoD = 1 << $d;
        $res = [];
        foreach ($a as $val) {
            $val = (($val % $q) + $q) % $q;
            $res[] = (int) round(($val * $twoD) / $q) % $twoD;
        }
        return $res;
    }

    /**
     * Decompress coefficient from d bits: round(y * q / 2^d).
     *
     * @param int[] $a
     * @param int $d
     * @return int[]
     */
    public static function decompress(array $a, int $d): array
    {
        $q = self::MODULUS;
        $twoD = 1 << $d;
        $res = [];
        foreach ($a as $val) {
            $res[] = (int) round(($val * $q) / $twoD) % $q;
        }
        return $res;
    }

    /**
     * Sample Centered Binomial Distribution CBD_eta from seed and nonce.
     *
     * @param string $seed 32-byte seed
     * @param int $nonce
     * @param int $eta (2 or 3)
     * @return int[] 256 coefficients in [-eta, eta] mapped mod q
     */
    public static function sampleCbd(string $seed, int $nonce, int $eta): array
    {
        $q = self::MODULUS;
        $n = self::DEGREE;
        $bytesNeeded = ($n * 2 * $eta) / 8; // 128 bytes for eta=2, 192 bytes for eta=3
        $pseudoRandomBytes = hash_hmac('sha512', $seed . pack('N', $nonce), 'NETFUSION_PQC_CBD', true);
        while (strlen($pseudoRandomBytes) < $bytesNeeded) {
            $pseudoRandomBytes .= hash('sha256', $pseudoRandomBytes, true);
        }

        $res = [];
        $byteIdx = 0;
        $bitBuf = 0;
        $bitsInBuf = 0;

        for ($i = 0; $i < $n; $i++) {
            $a = 0;
            $b = 0;
            for ($k = 0; $k < $eta; $k++) {
                if ($bitsInBuf === 0) {
                    $bitBuf = ord($pseudoRandomBytes[$byteIdx++]);
                    $bitsInBuf = 8;
                }
                $a += ($bitBuf & 1);
                $bitBuf >>= 1;
                $bitsInBuf--;
            }
            for ($k = 0; $k < $eta; $k++) {
                if ($bitsInBuf === 0) {
                    $bitBuf = ord($pseudoRandomBytes[$byteIdx++]);
                    $bitsInBuf = 8;
                }
                $b += ($bitBuf & 1);
                $bitBuf >>= 1;
                $bitsInBuf--;
            }
            $diff = $a - $b;
            $res[$i] = ($diff % $q + $q) % $q;
        }

        return $res;
    }

    /**
     * Sample uniform polynomial coefficients in [0, q-1] via rejection sampling.
     *
     * @param string $seed 32-byte seed
     * @param int $i Matrix row
     * @param int $j Matrix column
     * @return int[]
     */
    public static function sampleUniform(string $seed, int $i, int $j): array
    {
        $q = self::MODULUS;
        $n = self::DEGREE;
        $res = [];
        $stream = hash_hmac('sha512', $seed . pack('CC', $i, $j), 'NETFUSION_UNIFORM_MATRIX', true);
        $counter = 0;
        $pos = 0;

        while (count($res) < $n) {
            if ($pos + 2 > strlen($stream)) {
                $stream .= hash('sha256', $seed . pack('CCN', $i, $j, ++$counter), true);
            }
            $d1 = ord($stream[$pos++]);
            $d2 = ord($stream[$pos++]);
            $val = $d1 | (($d2 & 0x0F) << 8);
            if ($val < $q) {
                $res[] = $val;
            }
        }

        return $res;
    }

    /**
     * Bit reversal for 7-bit values.
     */
    private static function bitRev7(int $r): int
    {
        $res = 0;
        for ($i = 0; $i < 7; $i++) {
            $res = ($res << 1) | ($r & 1);
            $r >>= 1;
        }
        return $res;
    }

    /**
     * Modular exponentiation.
     */
    public static function modPow(int $base, int $exp, int $mod): int
    {
        if ($mod === 1) return 0;
        $res = 1;
        $base = $base % $mod;
        while ($exp > 0) {
            if ($exp & 1) {
                $res = ($res * $base) % $mod;
            }
            $exp >>= 1;
            $base = ($base * $base) % $mod;
        }
        return $res;
    }

    /**
     * Modular inverse using Extended Euclidean Algorithm.
     */
    public static function modInverse(int $a, int $m): int
    {
        $m0 = $m;
        $y = 0;
        $x = 1;
        if ($m === 1) return 0;
        while ($a > 1) {
            $q = (int) ($a / $m);
            $t = $m;
            $m = $a % $m;
            $a = $t;
            $t = $y;
            $y = $x - $q * $y;
            $x = $t;
        }
        if ($x < 0) {
            $x += $m0;
        }
        return $x;
    }
}
