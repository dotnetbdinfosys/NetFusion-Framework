<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Mechanisms/GaussianMechanism.php
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

namespace NetFusion\Core\Privacy\Differential\Mechanisms;

use InvalidArgumentException;
use NetFusion\Core\Privacy\Differential\Enums\MechanismType;

/**
 * Gaussian Mechanism for Approximate Differential Privacy ((ε, δ)-DP).
 * Adds noise drawn from Normal(0, σ^2) where σ = (Δ_2 f * sqrt(2 * ln(1.25 / δ))) / ε.
 */
class GaussianMechanism implements MechanismInterface
{
    public function getType(): MechanismType
    {
        return MechanismType::GAUSSIAN;
    }

    /**
     * @param float|int|array $value
     * @param float $epsilon
     * @param float $sensitivity L2 Global Sensitivity
     * @param float $delta Probability bound (0 < δ < 1)
     * @return float|int|array
     */
    public function addNoise(float|int|array $value, float $epsilon, float $sensitivity, float $delta = 1e-5): float|int|array
    {
        if ($epsilon <= 0.0) {
            throw new InvalidArgumentException("Epsilon must be strictly positive (ε > 0).");
        }
        if ($delta <= 0.0 || $delta >= 1.0) {
            throw new InvalidArgumentException("Delta must satisfy 0 < δ < 1 for Gaussian mechanism.");
        }
        if ($sensitivity < 0.0) {
            throw new InvalidArgumentException("Sensitivity must be non-negative.");
        }

        $sigma = ($sensitivity * sqrt(2.0 * log(1.25 / $delta))) / $epsilon;

        if (is_array($value)) {
            $noisy = [];
            foreach ($value as $k => $v) {
                $noisy[$k] = (float)$v + $this->sampleNormal($sigma);
            }
            return $noisy;
        }

        return (float)$value + $this->sampleNormal($sigma);
    }

    /**
     * Draw random sample from Normal(0, σ^2) using Box-Muller transform.
     */
    public function sampleNormal(float $sigma): float
    {
        if ($sigma <= 0.0) {
            return 0.0;
        }

        $u1 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $u2 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();

        $z0 = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
        return $z0 * $sigma;
    }
}
