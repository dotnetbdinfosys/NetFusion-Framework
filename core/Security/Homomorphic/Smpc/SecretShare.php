<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Smpc/SecretShare.php
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

/**
 * Single evaluation share (x, y) in Shamir's Secret Sharing scheme over finite field F_p.
 */
class SecretShare
{
    public function __construct(
        private int $x,
        private int $y,
        private int $prime
    ) {
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getPrime(): int
    {
        return $this->prime;
    }

    public function toArray(): array
    {
        return [
            'x'     => $this->x,
            'y'     => $this->y,
            'prime' => $this->prime,
        ];
    }
}
