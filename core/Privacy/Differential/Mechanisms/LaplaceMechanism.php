<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Mechanisms/LaplaceMechanism.php
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
 * Laplace Mechanism for Pure Differential Privacy (ε-DP).
 * Adds noise drawn from Laplace(0, b) where b = Δf / ε.
 */
class LaplaceMechanism implements MechanismInterface
{
    public function getType(): MechanismType
    {
        return MechanismType::LAPLACE;
    }

    /**
     * @param float|int|array $value
     * @param float $epsilon
     * @param float $sensitivity L1 Global Sensitivity
     * @param float $delta Ignored in pure Laplace mechanism
     * @return float|int|array
     */
    public function addNoise(float|int|array $value, float $epsilon, float $sensitivity, float $delta = 0.0): float|int|array
    {
        if ($epsilon <= 0.0) {
            throw new InvalidArgumentException("Epsilon must be strictly positive (ε > 0).");
        }
        if ($sensitivity < 0.0) {
            throw new InvalidArgumentException("Sensitivity must be non-negative.");
        }

        $scale = $sensitivity / $epsilon;

        if (is_array($value)) {
            $noisy = [];
            foreach ($value as $k => $v) {
                $noisy[$k] = (float)$v + $this->sampleLaplace($scale);
            }
            return $noisy;
        }

        return (float)$value + $this->sampleLaplace($scale);
    }

    /**
     * Draw random sample from Laplace(0, b) using Inverse Transform Sampling.
     * X = -b * sgn(u) * ln(1 - 2|u|) where u ~ Uniform(-0.5, 0.5)
     */
    public function sampleLaplace(float $scale): float
    {
        if ($scale <= 0.0) {
            return 0.0;
        }

        // Generate uniform random float in (-0.5, 0.5)
        $u = (mt_rand(1, mt_getrandmax() - 1) / mt_getrandmax()) - 0.5;
        $sign = $u >= 0 ? 1.0 : -1.0;

        return -$scale * $sign * log(1.0 - 2.0 * abs($u));
    }
}
