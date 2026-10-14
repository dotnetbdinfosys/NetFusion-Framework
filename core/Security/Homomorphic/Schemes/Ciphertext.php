<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Schemes/Ciphertext.php
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

/**
 * Homomorphic Ciphertext representation.
 * Pair of polynomials (c0, c1) in R_q, or degree 2 tuple (c0, c1, c2) after non-relinearized multiplication.
 */
class Ciphertext
{
    /**
     * @param array<int, int[]> $elements Polynomial elements [c0, c1] or [c0, c1, c2]
     * @param SchemeType $scheme
     * @param float $scale CKKS scale factor
     * @param int $noiseEstimate Noise level estimator
     */
    public function __construct(
        private array $elements,
        private SchemeType $scheme = SchemeType::BFV,
        private float $scale = 1.0,
        private int $noiseEstimate = 1
    ) {
    }

    /**
     * @return array<int, int[]>
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    public function getC0(): array
    {
        return $this->elements[0];
    }

    public function getC1(): array
    {
        return $this->elements[1];
    }

    public function getDegree(): int
    {
        return count($this->elements) - 1;
    }

    public function getScheme(): SchemeType
    {
        return $this->scheme;
    }

    public function getScale(): float
    {
        return $this->scale;
    }

    public function getNoiseEstimate(): int
    {
        return $this->noiseEstimate;
    }

    public function toArray(): array
    {
        return [
            'scheme'        => $this->scheme->value,
            'scale'         => $this->scale,
            'degree'        => $this->getDegree(),
            'noiseEstimate' => $this->noiseEstimate,
            'c0_head'       => array_slice($this->elements[0] ?? [], 0, 4),
            'c1_head'       => array_slice($this->elements[1] ?? [], 0, 4),
        ];
    }
}
