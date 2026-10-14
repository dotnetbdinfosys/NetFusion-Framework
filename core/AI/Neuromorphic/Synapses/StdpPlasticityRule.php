<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Synapses/StdpPlasticityRule.php
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

namespace NetFusion\Core\AI\Neuromorphic\Synapses;

/**
 * Spike-Timing-Dependent Plasticity (STDP) Learning Rule.
 *
 * Implements exponential biophysical Hebbian synaptic weight adjustment:
 *   Delta w = A_plus  * exp(-Delta t / tau_plus)  if Delta t > 0 (LTP - Potentiation)
 *   Delta w = -A_minus * exp(Delta t / tau_minus)  if Delta t < 0 (LTD - Depression)
 * Where Delta t = t_post - t_pre.
 */
class StdpPlasticityRule
{
    public function __construct(
        private readonly float $aPlus = 0.01,       // Potentiation amplitude
        private readonly float $aMinus = 0.012,     // Depression amplitude (slightly larger for stability)
        private readonly float $tauPlus = 20.0,     // Potentiation time window (ms)
        private readonly float $tauMinus = 20.0,    // Depression time window (ms)
        private readonly float $wMin = 0.0,         // Minimum bounded weight
        private readonly float $wMax = 1.0          // Maximum bounded weight
    ) {
    }

    /**
     * Compute weight change Delta w and return updated bounded synaptic weight.
     *
     * @param float $currentWeight Current synaptic weight
     * @param float $tPre Pre-synaptic spike arrival timestamp (ms)
     * @param float $tPost Post-synaptic action potential spike timestamp (ms)
     * @return array{delta_w: float, new_weight: float}
     */
    public function update(float $currentWeight, float $tPre, float $tPost): array
    {
        $dt = $tPost - $tPre;

        if (abs($dt) < 1e-6) {
            return ['delta_w' => 0.0, 'new_weight' => $currentWeight];
        }

        if ($dt > 0.0) {
            // Pre precedes Post -> Long-Term Potentiation (LTP)
            $deltaW = $this->aPlus * exp(-$dt / max(0.001, $this->tauPlus));
        } else {
            // Post precedes Pre -> Long-Term Depression (LTD)
            $deltaW = -$this->aMinus * exp($dt / max(0.001, $this->tauMinus));
        }

        $newWeight = max($this->wMin, min($this->wMax, $currentWeight + $deltaW));

        return [
            'delta_w'    => $deltaW,
            'new_weight' => $newWeight,
        ];
    }

    public function getAPlus(): float
    {
        return $this->aPlus;
    }

    public function getAMinus(): float
    {
        return $this->aMinus;
    }

    public function getTauPlus(): float
    {
        return $this->tauPlus;
    }

    public function getTauMinus(): float
    {
        return $this->tauMinus;
    }
}
