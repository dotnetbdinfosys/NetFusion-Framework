<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Mechanisms/RandomizedResponse.php
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
 * Randomized Response Mechanism for Local Differential Privacy (LDP).
 * Allows individuals to provide randomized answers guaranteeing ε-LDP:
 * P(report = true | truth = true) = e^ε / (1 + e^ε)
 */
class RandomizedResponse implements MechanismInterface
{
    public function getType(): MechanismType
    {
        return MechanismType::RANDOMIZED_RESPONSE;
    }

    public function addNoise(float|int|array $value, float $epsilon, float $sensitivity = 1.0, float $delta = 0.0): float|int|array
    {
        if (is_array($value)) {
            $res = [];
            foreach ($value as $k => $v) {
                $res[$k] = $this->perturbBoolean((bool)$v, $epsilon) ? 1 : 0;
            }
            return $res;
        }

        return $this->perturbBoolean((bool)$value, $epsilon) ? 1 : 0;
    }

    /**
     * Perturb a boolean answer with probability p = exp(ε) / (1 + exp(ε)).
     */
    public function perturbBoolean(bool $trueValue, float $epsilon): bool
    {
        if ($epsilon <= 0.0) {
            throw new InvalidArgumentException("Epsilon must be strictly positive (ε > 0).");
        }

        $p = exp($epsilon) / (1.0 + exp($epsilon));
        $rand = mt_rand(1, mt_getrandmax()) / mt_getrandmax();

        // With probability p, report truth. With 1-p, report negation.
        return ($rand <= $p) ? $trueValue : !$trueValue;
    }

    /**
     * Unbias and reconstruct the true population proportion from randomized responses.
     * Estimated Proportion = (noisyProportion + p - 1) / (2p - 1)
     */
    public function estimateProportion(array $noisyResponses, float $epsilon): float
    {
        if (empty($noisyResponses)) {
            return 0.0;
        }

        $count = count($noisyResponses);
        $yesCount = 0;
        foreach ($noisyResponses as $resp) {
            if ($resp) {
                $yesCount++;
            }
        }

        $noisyP = $yesCount / $count;
        $p = exp($epsilon) / (1.0 + exp($epsilon));

        if (abs(2.0 * $p - 1.0) < 1e-9) {
            return $noisyP;
        }

        $estimated = ($noisyP + $p - 1.0) / (2.0 * $p - 1.0);
        return max(0.0, min(1.0, $estimated));
    }
}
