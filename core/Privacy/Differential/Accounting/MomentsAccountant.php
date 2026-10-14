<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Accounting/MomentsAccountant.php
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

namespace NetFusion\Core\Privacy\Differential\Accounting;

/**
 * Moments Accountant / Rényi Differential Privacy (RDP) Tracker (Abadi et al.).
 * Tracks higher-order moments (alpha-divergences) yielding tighter composition for subsampled Gaussian mechanisms.
 */
class MomentsAccountant
{
    /** @var array<int, float> Log-moments indexed by moment order lambda */
    private array $logMoments = [];

    public function __construct(private array $orders = [2, 4, 8, 16, 32, 64])
    {
        foreach ($this->orders as $lambda) {
            $this->logMoments[$lambda] = 0.0;
        }
    }

    /**
     * Accumulate moment for subsampled Gaussian mechanism step.
     *
     * @param float $q Subsampling ratio (batch_size / dataset_size)
     * @param float $sigma Noise multiplier (noise_std / clipping_norm)
     * @param int $steps Number of iterations
     */
    public function accumulate(float $q, float $sigma, int $steps = 1): void
    {
        if ($q <= 0.0 || $sigma <= 0.0) {
            return;
        }

        foreach ($this->orders as $lambda) {
            // Asymptotic upper bound for RDP of Gaussian mechanism:
            // alpha_moment = lambda / (2 * sigma^2)
            $stepMoment = ($q * $q * $lambda) / (2.0 * $sigma * $sigma);
            $this->logMoments[$lambda] += $stepMoment * $steps;
        }
    }

    /**
     * Convert accumulated Rényi moments to (ε, δ)-DP:
     * ε(δ) = min_lambda { logMoments[lambda] + ln(1 / δ) / (lambda - 1) }
     */
    public function getEpsilon(float $targetDelta = 1e-5): float
    {
        $minEps = INF;
        foreach ($this->orders as $lambda) {
            if ($lambda <= 1) {
                continue;
            }
            $eps = $this->logMoments[$lambda] + (log(1.0 / $targetDelta) / ($lambda - 1.0));
            if ($eps < $minEps) {
                $minEps = $eps;
            }
        }

        return $minEps === INF ? 0.0 : $minEps;
    }

    public function getLogMoments(): array
    {
        return $this->logMoments;
    }
}
