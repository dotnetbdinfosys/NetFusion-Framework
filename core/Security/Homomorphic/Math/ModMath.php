<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Math/ModMath.php
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
 * Prime field and modular integer arithmetic helpers.
 */
class ModMath
{
    /**
     * Compute (a % m) strictly non-negative in [0, m - 1].
     */
    public static function mod(int|float $a, int $m): int
    {
        $val = (int)$a % $m;
        return $val < 0 ? $val + $m : $val;
    }

    /**
     * Modular addition (a + b) mod m.
     */
    public static function add(int $a, int $b, int $m): int
    {
        return self::mod($a + $b, $m);
    }

    /**
     * Modular subtraction (a - b) mod m.
     */
    public static function sub(int $a, int $b, int $m): int
    {
        return self::mod($a - $b, $m);
    }

    /**
     * Modular multiplication (a * b) mod m.
     */
    public static function mul(int $a, int $b, int $m): int
    {
        return self::mod($a * $b, $m);
    }

    /**
     * Modular exponentiation (base^exp) mod m using repeated squaring.
     */
    public static function pow(int $base, int $exp, int $m): int
    {
        if ($exp < 0) {
            $base = self::inverse($base, $m);
            $exp = abs($exp);
        }

        $res = 1;
        $base = self::mod($base, $m);
        while ($exp > 0) {
            if ($exp & 1) {
                $res = self::mul($res, $base, $m);
            }
            $base = self::mul($base, $base, $m);
            $exp >>= 1;
        }
        return $res;
    }

    /**
     * Extended Euclidean Algorithm: finds g, x, y such that ax + by = gcd(a, b).
     */
    public static function extGcd(int $a, int $b): array
    {
        if ($b === 0) {
            return [$a, 1, 0];
        }
        [$g, $x1, $y1] = self::extGcd($b, $a % $b);
        $x = $y1;
        $y = $x1 - ((int)floor($a / $b)) * $y1;
        return [$g, $x, $y];
    }

    /**
     * Modular multiplicative inverse of a mod m (a * a^-1 = 1 mod m).
     */
    public static function inverse(int $a, int $m): int
    {
        [$g, $x, ] = self::extGcd(self::mod($a, $m), $m);
        if ($g !== 1) {
            throw new \InvalidArgumentException("No modular inverse exists for {$a} modulo {$m} (gcd = {$g}).");
        }
        return self::mod($x, $m);
    }

    /**
     * Alias for inverse().
     */
    public static function inv(int $a, int $m): int
    {
        return self::inverse($a, $m);
    }
}
