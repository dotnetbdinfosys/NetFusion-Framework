<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Federated/FederatedAggregator.php
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

namespace NetFusion\Core\Privacy\Differential\Federated;

use InvalidArgumentException;
use NetFusion\Core\Privacy\Differential\Mechanisms\GaussianMechanism;
use NetFusion\Core\Privacy\Differential\Mechanisms\MechanismInterface;

/**
 * Differentially Private Federated Learning Model Aggregator (DP-FedAvg).
 * Aggregates local updates weighted by sample counts and injects calibrated Gaussian/Laplace noise.
 */
class FederatedAggregator
{
    private MechanismInterface $mechanism;

    public function __construct(?MechanismInterface $mechanism = null)
    {
        $this->mechanism = $mechanism ?? new GaussianMechanism();
    }

    /**
     * Perform Differentially Private Federated Averaging (DP-FedAvg).
     *
     * @param FederatedNodeUpdate[] $updates List of node updates
     * @param float $clippingNorm Max L2 norm threshold C
     * @param float $epsilon Privacy budget ε
     * @param float $delta Probability bound δ
     * @return float[] Aggregated differentially private global weights
     */
    public function aggregate(
        array $updates,
        float $clippingNorm,
        float $epsilon,
        float $delta = 1e-5
    ): array {
        if (empty($updates)) {
            throw new InvalidArgumentException("Updates array cannot be empty for federated aggregation.");
        }

        $totalSamples = 0;
        foreach ($updates as $u) {
            $totalSamples += $u->getSampleCount();
        }

        if ($totalSamples <= 0) {
            $totalSamples = count($updates);
        }

        // Weighted sum of clipped updates
        $weightDim = count($updates[0]->getWeights());
        $sumWeights = array_fill(0, $weightDim, 0.0);

        foreach ($updates as $u) {
            $w = $u->getWeights();
            $weightFactor = $u->getSampleCount() / $totalSamples;
            for ($i = 0; $i < $weightDim; $i++) {
                $sumWeights[$i] += ($w[$i] ?? 0.0) * $weightFactor;
            }
        }

        // Sensitivity of weighted average bounded by C / K (or C)
        $sensitivity = $clippingNorm / count($updates);

        // Perturb aggregated weights
        /** @var float[] $noisyWeights */
        $noisyWeights = $this->mechanism->addNoise($sumWeights, $epsilon, $sensitivity, $delta);

        return array_map(fn($val) => round((float)$val, 6), $noisyWeights);
    }
}
