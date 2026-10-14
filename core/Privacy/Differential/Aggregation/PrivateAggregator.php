<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Aggregation/PrivateAggregator.php
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

namespace NetFusion\Core\Privacy\Differential\Aggregation;

use NetFusion\Core\Privacy\Differential\Accounting\PrivacyBudget;
use NetFusion\Core\Privacy\Differential\Mechanisms\LaplaceMechanism;
use NetFusion\Core\Privacy\Differential\Mechanisms\MechanismInterface;

/**
 * Differentially Private Aggregation Suite (Count, Sum, Mean, Histogram).
 */
class PrivateAggregator
{
    private MechanismInterface $mechanism;

    public function __construct(?MechanismInterface $mechanism = null)
    {
        $this->mechanism = $mechanism ?? new LaplaceMechanism();
    }

    public function setMechanism(MechanismInterface $mechanism): self
    {
        $this->mechanism = $mechanism;
        return $this;
    }

    /**
     * Private COUNT aggregation.
     * True sensitivity = 1.0.
     */
    public function privateCount(int|float $trueCount, float $epsilon, ?PrivacyBudget $budget = null): float
    {
        if ($budget !== null) {
            $budget->consume($epsilon, 0.0, 'COUNT');
        }

        $sens = SensitivityAnalyzer::countSensitivity();
        $noisy = (float)$this->mechanism->addNoise($trueCount, $epsilon, $sens);
        return max(0.0, round($noisy, 4));
    }

    /**
     * Private SUM aggregation with bounded values.
     * Values are clipped to [lowerBound, upperBound].
     */
    public function privateSum(array $values, float $lowerBound, float $upperBound, float $epsilon, ?PrivacyBudget $budget = null): float
    {
        if ($budget !== null) {
            $budget->consume($epsilon, 0.0, 'SUM');
        }

        $clippedSum = 0.0;
        foreach ($values as $v) {
            $clippedSum += SensitivityAnalyzer::clip((float)$v, $lowerBound, $upperBound);
        }

        $sens = SensitivityAnalyzer::sumSensitivity($lowerBound, $upperBound);
        $noisy = (float)$this->mechanism->addNoise($clippedSum, $epsilon, $sens);
        return round($noisy, 4);
    }

    /**
     * Private MEAN aggregation using split privacy budget:
     * ε_sum = ε / 2, ε_count = ε / 2
     */
    public function privateMean(array $values, float $lowerBound, float $upperBound, float $epsilon, ?PrivacyBudget $budget = null): float
    {
        $halfEps = $epsilon / 2.0;

        $noisySum = $this->privateSum($values, $lowerBound, $upperBound, $halfEps, $budget);
        $noisyCount = $this->privateCount(count($values), $halfEps, $budget);

        if ($noisyCount <= 0.0) {
            return ($lowerBound + $upperBound) / 2.0;
        }

        $mean = $noisySum / $noisyCount;
        return round(SensitivityAnalyzer::clip($mean, $lowerBound, $upperBound), 4);
    }

    /**
     * Private Histogram: adds independent Laplace noise to each bin count.
     * Parallel composition applies: entire histogram costs ε (since each individual row falls in at most 1 bin).
     *
     * @param array<string, int> $bins [category => count]
     * @param float $epsilon
     * @param PrivacyBudget|null $budget
     * @return array<string, float>
     */
    public function privateHistogram(array $bins, float $epsilon, ?PrivacyBudget $budget = null): array
    {
        if ($budget !== null) {
            $budget->consume($epsilon, 0.0, 'HISTOGRAM');
        }

        $noisyBins = [];
        $sens = SensitivityAnalyzer::countSensitivity();

        foreach ($bins as $category => $count) {
            $noisy = (float)$this->mechanism->addNoise((float)$count, $epsilon, $sens);
            $noisyBins[$category] = max(0.0, round($noisy, 4));
        }

        return $noisyBins;
    }
}
