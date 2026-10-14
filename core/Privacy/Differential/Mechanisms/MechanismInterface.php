<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Mechanisms/MechanismInterface.php
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

use NetFusion\Core\Privacy\Differential\Enums\MechanismType;

/**
 * Interface contract for Differential Privacy mechanisms.
 */
interface MechanismInterface
{
    public function getType(): MechanismType;

    /**
     * Add calibrated noise to a numerical or composite value.
     *
     * @param float|int|array $value Raw true query result or gradient vector
     * @param float $epsilon Privacy budget epsilon (ε > 0)
     * @param float $sensitivity Global sensitivity (Δf > 0)
     * @param float $delta Probability bound for approximate DP (δ >= 0)
     * @return float|int|array Perturbed differentially private result
     */
    public function addNoise(float|int|array $value, float $epsilon, float $sensitivity, float $delta = 0.0): float|int|array;
}
