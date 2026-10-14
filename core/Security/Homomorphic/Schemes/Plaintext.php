<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Schemes/Plaintext.php
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

/**
 * Plaintext representation in polynomial form.
 */
class Plaintext
{
    /**
     * @param int[] $poly Polynomial coefficients in Z_t
     * @param float|null $scale Scale factor for CKKS fixed-point encoding
     */
    public function __construct(
        private array $poly,
        private ?float $scale = null
    ) {
    }

    /**
     * @return int[]
     */
    public function getPoly(): array
    {
        return $this->poly;
    }

    public function getScale(): ?float
    {
        return $this->scale;
    }

    public function getLeadingValue(): int
    {
        return $this->poly[0] ?? 0;
    }
}
