<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Encoding/SpikeDecoder.php
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

/**
 * Temporal Neural Spike Train Decoder.
 *
 * Decodes spatio-temporal spike trains into continuous predictions or classification decisions.
 */
class SpikeDecoder
{
    /**
     * Decode spike train into normalized firing rates per channel.
     *
     * @param array<int, array<int, int>> $spikeMatrix [timestep => [channel => 0|1]]
     * @return float[] [channel => rate]
     */
    public function decodeRate(array $spikeMatrix): array
    {
        $timesteps = count($spikeMatrix);
        if ($timesteps === 0) {
            return [];
        }

        $channels = count($spikeMatrix[0]);
        $counts = array_fill(0, $channels, 0);

        foreach ($spikeMatrix as $stepSpikes) {
            foreach ($stepSpikes as $c => $spike) {
                if ($spike === 1) {
                    $counts[$c]++;
                }
            }
        }

        $rates = [];
        foreach ($counts as $c => $count) {
            $rates[$c] = round($count / $timesteps, 4);
        }

        return $rates;
    }

    /**
     * Decode Time-to-First-Spike latency into continuous predictions.
     *
     * @param array<int, array<int, int>> $spikeMatrix [timestep => [channel => 0|1]]
     * @return float[] [channel => latency_score]
     */
    public function decodeFirstSpike(array $spikeMatrix): array
    {
        $timesteps = count($spikeMatrix);
        if ($timesteps === 0) {
            return [];
        }

        $channels = count($spikeMatrix[0]);
        $firstSpikeTimes = array_fill(0, $channels, null);

        foreach ($spikeMatrix as $t => $stepSpikes) {
            foreach ($stepSpikes as $c => $spike) {
                if ($spike === 1 && $firstSpikeTimes[$c] === null) {
                    $firstSpikeTimes[$c] = $t;
                }
            }
        }

        $scores = [];
        foreach ($firstSpikeTimes as $c => $t) {
            if ($t === null) {
                $scores[$c] = 0.0;
            } else {
                // Inverted latency: earlier spike -> higher score closer to 1.0
                $scores[$c] = round(1.0 - ($t / max(1, $timesteps - 1)), 4);
            }
        }

        return $scores;
    }

    /**
     * Decode output spikes into predicted discrete class index and confidence.
     *
     * @param array<int, array<int, int>> $spikeMatrix
     * @return array{predicted_class: int, total_spikes: int, confidence: float, class_counts: array<int, int>}
     */
    public function decodeClassification(array $spikeMatrix): array
    {
        $timesteps = count($spikeMatrix);
        if ($timesteps === 0) {
            return ['predicted_class' => 0, 'total_spikes' => 0, 'confidence' => 0.0, 'class_counts' => []];
        }

        $channels = count($spikeMatrix[0]);
        $counts = array_fill(0, $channels, 0);

        foreach ($spikeMatrix as $stepSpikes) {
            foreach ($stepSpikes as $c => $spike) {
                if ($spike === 1) {
                    $counts[$c]++;
                }
            }
        }

        $totalSpikes = array_sum($counts);
        $maxCount = -1;
        $predictedClass = 0;

        foreach ($counts as $c => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $predictedClass = $c;
            }
        }

        $confidence = $totalSpikes > 0 ? round($maxCount / $totalSpikes, 4) : 0.0;

        return [
            'predicted_class' => $predictedClass,
            'total_spikes'    => $totalSpikes,
            'confidence'      => $confidence,
            'class_counts'    => $counts,
        ];
    }
}
