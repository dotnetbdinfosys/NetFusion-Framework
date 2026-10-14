<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Smpc/BeaverTriple.php
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

namespace NetFusion\Core\Security\Homomorphic\Smpc;

use NetFusion\Core\Security\Homomorphic\Math\ModMath;

/**
 * Beaver Multiplication Triple (a, b, c = a * b mod p).
 * Pre-shared randomized correlation allowing secure linear multiplication without revealing inputs.
 */
class BeaverTriple
{
    public function __construct(
        private int $a,
        private int $b,
        private int $c,
        private int $prime
    ) {
    }

    public static function generate(int $prime = 2147483647): self
    {
        $a = mt_rand(1, $prime - 1);
        $b = mt_rand(1, $prime - 1);
        $c = ModMath::mul($a, $b, $prime);
        return new self($a, $b, $c, $prime);
    }

    public function isValid(): bool
    {
        return ModMath::mul($this->a, $this->b, $this->prime) === $this->c;
    }

    public function getA(): int
    {
        return $this->a;
    }

    public function getB(): int
    {
        return $this->b;
    }

    public function getC(): int
    {
        return $this->c;
    }

    public function getPrime(): int
    {
        return $this->prime;
    }
}
