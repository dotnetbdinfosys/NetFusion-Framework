<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\ZKP\Math;

/**
 * High-precision Big Integer and Modular Arithmetic wrapper using GMP.
 */
class BigInt
{
    private \GMP $val;

    public function __construct(int|string|\GMP $val = 0)
    {
        $this->val = self::toGmp($val);
    }

    public static function of(int|string|\GMP|self $val): self
    {
        if ($val instanceof self) {
            return $val;
        }
        return new self($val);
    }

    private static function toGmp(self|int|string|\GMP $val): \GMP
    {
        if ($val instanceof self) {
            return $val->val;
        }
        if ($val instanceof \GMP) {
            return $val;
        }
        return gmp_init($val);
    }

    public function add(BigInt|int|string|\GMP $b): self
    {
        return new self(gmp_add($this->val, self::toGmp($b)));
    }

    public function sub(BigInt|int|string|\GMP $b): self
    {
        return new self(gmp_sub($this->val, self::toGmp($b)));
    }

    public function mul(BigInt|int|string|\GMP $b): self
    {
        return new self(gmp_mul($this->val, self::toGmp($b)));
    }

    public function mod(BigInt|int|string|\GMP $modulus): self
    {
        $m = self::toGmp($modulus);
        $res = gmp_mod($this->val, $m);
        if (gmp_sign($res) < 0) {
            $res = gmp_add($res, $m);
        }
        return new self($res);
    }

    public function modPow(BigInt|int|string|\GMP $exp, BigInt|int|string|\GMP $modulus): self
    {
        return new self(gmp_powm(
            $this->val,
            self::toGmp($exp),
            self::toGmp($modulus)
        ));
    }

    public function modInverse(BigInt|int|string|\GMP $modulus): self
    {
        return new self(gmp_invert(
            $this->val,
            self::toGmp($modulus)
        ));
    }

    public function equals(BigInt|int|string|\GMP $b): bool
    {
        return gmp_cmp($this->val, self::toGmp($b)) === 0;
    }

    public function compareTo(BigInt|int|string|\GMP $b): int
    {
        return gmp_cmp($this->val, self::toGmp($b));
    }


    public function isZero(): bool
    {
        return gmp_sign($this->val) === 0;
    }

    public function getGmp(): \GMP
    {
        return $this->val;
    }

    public function toString(int $base = 10): string
    {
        return gmp_strval($this->val, $base);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
