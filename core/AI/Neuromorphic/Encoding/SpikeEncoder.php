<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Encoding/SpikeEncoder.php
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

namespace NetFusion\Core\AI\Neuromorphic\Encoding;

use NetFusion\Core\AI\Neuromorphic\Enums\EncodingScheme;

/**
 * Temporal Neural Spike Train Encoder.
 *
 * Converts continuous floating-point features into discrete binary spike matrices [timesteps x channels].
 */
class SpikeEncoder
{
    /**
     * Encode continuous values into Rate-coded spike trains.
     *
     * @param float[] $values Normalized values in [0.0, 1.0]
     * @param int $timesteps Duration of simulation window
     * @param bool $deterministic Uniform firing interval vs stochastic Poisson
     * @return array<int, array<int, int>> [timestep => [channel => 0|1]]
     */
    public function encodeRate(array $values, int $timesteps = 50, bool $deterministic = false): array
    {
        $spikeMatrix = [];
        $channels = count($values);

        for ($t = 0; $t < $timesteps; $t++) {
            $spikeMatrix[$t] = array_fill(0, $channels, 0);
            for ($c = 0; $c < $channels; $c++) {
                $val = max(0.0, min(1.0, (float)$values[$c]));
                if ($deterministic) {
                    $period = $val > 0.0 ? (int)round(1.0 / $val) : 0;
                    if ($period > 0 && ($t % $period === 0)) {
                        $spikeMatrix[$t][$c] = 1;
                    }
                } else {
                    $prob = mt_rand(1, 10000) / 10000.0;
                    if ($prob <= $val) {
                        $spikeMatrix[$t][$c] = 1;
                    }
                }
            }
        }

        return $spikeMatrix;
    }

    /**
     * Encode continuous values into Time-To-First-Spike (TTFS / Latency coding).
     *
     * Higher value fires earlier in the temporal window.
     *
     * @param float[] $values Normalized values in [0.0, 1.0]
     * @param int $timesteps Duration of temporal window
     * @return array<int, array<int, int>> [timestep => [channel => 0|1]]
     */
    public function encodeTtfs(array $values, int $timesteps = 50): array
    {
        $spikeMatrix = [];
        $channels = count($values);

        // Pre-initialize matrix with zeros
        for ($t = 0; $t < $timesteps; $t++) {
            $spikeMatrix[$t] = array_fill(0, $channels, 0);
        }

        for ($c = 0; $c < $channels; $c++) {
            $val = max(0.0, min(1.0, (float)$values[$c]));
            if ($val > 0.0) {
                // Higher values spike closer to t = 0
                $fireStep = (int)round((1.0 - $val) * ($timesteps - 1));
                $fireStep = max(0, min($timesteps - 1, $fireStep));
                $spikeMatrix[$fireStep][$c] = 1;
            }
        }

        return $spikeMatrix;
    }

    /**
     * Encode streaming time-series signal using event-driven delta thresholding.
     *
     * Generates a spike only when the absolute change |x_t - x_prev| >= threshold.
     *
     * @param float[] $timeSeries Sequential continuous signal samples
     * @param float $threshold Relative change threshold
     * @return array<int, int> [timestep => 0|1]
     */
    public function encodeDelta(array $timeSeries, float $threshold = 0.1): array
    {
        $spikes = [];
        $lastValue = null;

        foreach ($timeSeries as $t => $val) {
            $val = (float)$val;
            if ($lastValue === null) {
                $lastValue = $val;
                $spikes[$t] = 0;
                continue;
            }

            if (abs($val - $lastValue) >= $threshold) {
                $spikes[$t] = 1;
                $lastValue = $val;
            } else {
                $spikes[$t] = 0;
            }
        }

        return $spikes;
    }

    /**
     * Unified encoder dispatch.
     *
     * @param float[] $values
     * @param EncodingScheme $scheme
     * @param int $timesteps
     * @return array<int, array<int, int>>
     */
    public function encode(array $values, EncodingScheme $scheme = EncodingScheme::RATE, int $timesteps = 50): array
    {
        return match ($scheme) {
            EncodingScheme::RATE       => $this->encodeRate($values, $timesteps, deterministic: true),
            EncodingScheme::TTFS       => $this->encodeTtfs($values, $timesteps),
            EncodingScheme::DELTA      => array_map(fn($s) => [$s], $this->encodeDelta($values)),
            EncodingScheme::POPULATION => $this->encodeRate($values, $timesteps, deterministic: false),
        };
    }
}
