<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Mechanisms/ExponentialMechanism.php
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
 * Exponential Mechanism for Differentially Private Selection over Discrete Candidates.
 * Selects candidate r with probability proportional to exp( (ε * u(x, r)) / (2 * Δu) ).
 */
class ExponentialMechanism implements MechanismInterface
{
    public function getType(): MechanismType
    {
        return MechanismType::EXPONENTIAL;
    }

    public function addNoise(float|int|array $value, float $epsilon, float $sensitivity, float $delta = 0.0): float|int|array
    {
        // For numeric array of utility scores, perturb or select
        return $value;
    }

    /**
     * Choose candidate from an array of candidates with evaluated utility scores.
     *
     * @param array<string|int, mixed> $candidates List of possible outcomes
     * @param callable $utilityFn Function signature: fn($candidate): float
     * @param float $epsilon Privacy budget ε
     * @param float $sensitivity Sensitivity of utility function Δu
     * @return mixed Selected candidate
     */
    public function select(array $candidates, callable $utilityFn, float $epsilon, float $sensitivity = 1.0): mixed
    {
        if (empty($candidates)) {
            throw new InvalidArgumentException("Candidates array cannot be empty.");
        }
        if ($epsilon <= 0.0) {
            throw new InvalidArgumentException("Epsilon must be strictly positive (ε > 0).");
        }
        if ($sensitivity <= 0.0) {
            throw new InvalidArgumentException("Utility sensitivity must be strictly positive.");
        }

        $scores = [];
        $maxScore = -INF;
        foreach ($candidates as $key => $candidate) {
            $u = (float)$utilityFn($candidate);
            $scaled = ($epsilon * $u) / (2.0 * $sensitivity);
            $scores[$key] = $scaled;
            if ($scaled > $maxScore) {
                $maxScore = $scaled;
            }
        }

        // Numerical stability: subtract maxScore before exponentiating
        $weights = [];
        $totalWeight = 0.0;
        foreach ($scores as $key => $scaled) {
            $w = exp($scaled - $maxScore);
            $weights[$key] = $w;
            $totalWeight += $w;
        }

        // Weighted random sample
        $rand = (mt_rand(1, mt_getrandmax()) / mt_getrandmax()) * $totalWeight;
        $acc = 0.0;
        foreach ($weights as $key => $w) {
            $acc += $w;
            if ($acc >= $rand) {
                return $candidates[$key];
            }
        }

        // Fallback to last item
        $lastKeys = array_keys($candidates);
        return $candidates[end($lastKeys)];
    }
}
