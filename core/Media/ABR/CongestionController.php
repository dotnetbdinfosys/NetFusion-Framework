<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Media\ABR;

use NetFusion\Core\Media\Enums\SimulcastLayer;

/**
 * WebRTC Congestion Controller & Bandwidth Estimator.
 * Implements loss-based and delay-based adaptation inspired by Google Congestion Control (GCC).
 */
class CongestionController
{
    protected int $minBandwidthKbps;
    protected int $maxBandwidthKbps;
    protected int $currentBandwidthKbps;
    protected float $lastLossFraction = 0.0;
    protected int $lastRttMs = 0;
    protected string $state = 'HOLD'; // 'INCREASING', 'DECREASING', 'HOLD'

    public function __construct(
        int $initialBandwidthKbps = 1500,
        int $minBandwidthKbps = 150,
        int $maxBandwidthKbps = 8000
    ) {
        $this->minBandwidthKbps = $minBandwidthKbps;
        $this->maxBandwidthKbps = $maxBandwidthKbps;
        $this->currentBandwidthKbps = max($minBandwidthKbps, min($maxBandwidthKbps, $initialBandwidthKbps));
    }

    /**
     * Process RTCP Receiver Report loss fraction (0.0 - 1.0) and RTT (ms).
     */
    public function updateLossFeedback(float $lossFraction, int $rttMs = 50): int
    {
        $this->lastLossFraction = max(0.0, min(1.0, $lossFraction));
        $this->lastRttMs = max(0, $rttMs);

        // Standard WebRTC GCC threshold logic:
        // Loss > 10%: Network congested -> back off exponentially
        if ($this->lastLossFraction > 0.10) {
            $decreaseFactor = 1.0 - (0.5 * $this->lastLossFraction);
            $this->currentBandwidthKbps = (int) round($this->currentBandwidthKbps * $decreaseFactor);
            $this->state = 'DECREASING';
        }
        // Loss < 2%: Network has headroom -> increase conservatively (AIMD)
        elseif ($this->lastLossFraction < 0.02) {
            $additiveIncrease = 50; // kbps
            $multiplicativeIncrease = 1.05;
            $this->currentBandwidthKbps = (int) round($this->currentBandwidthKbps * $multiplicativeIncrease + $additiveIncrease);
            $this->state = 'INCREASING';
        }
        // Loss between 2% and 10%: Network in equilibrium
        else {
            $this->state = 'HOLD';
        }

        // Clamp
        $this->currentBandwidthKbps = max($this->minBandwidthKbps, min($this->maxBandwidthKbps, $this->currentBandwidthKbps));

        return $this->currentBandwidthKbps;
    }

    /**
     * Process RTCP REMB (Receiver Estimated Maximum Bitrate).
     */
    public function updateRembFeedback(int $rembBitrateBps): int
    {
        $rembKbps = (int) ($rembBitrateBps / 1000);
        $this->currentBandwidthKbps = max($this->minBandwidthKbps, min($this->maxBandwidthKbps, $rembKbps));
        return $this->currentBandwidthKbps;
    }

    public function getEstimatedBandwidthKbps(): int
    {
        return $this->currentBandwidthKbps;
    }

    public function getLastLossFraction(): float
    {
        return $this->lastLossFraction;
    }

    public function getLastRttMs(): int
    {
        return $this->lastRttMs;
    }

    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Determine recommended SimulcastLayer based on estimated bandwidth.
     */
    public function getRecommendedLayer(): SimulcastLayer
    {
        if ($this->currentBandwidthKbps >= 1500) {
            return SimulcastLayer::HIGH;
        }

        if ($this->currentBandwidthKbps >= 500) {
            return SimulcastLayer::MEDIUM;
        }

        return SimulcastLayer::LOW;
    }

    /**
     * Summary statistics of the controller.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bandwidthKbps' => $this->currentBandwidthKbps,
            'state' => $this->state,
            'recommendedLayer' => $this->getRecommendedLayer()->value,
            'lossFraction' => $this->lastLossFraction,
            'rttMs' => $this->lastRttMs,
            'minKbps' => $this->minBandwidthKbps,
            'maxKbps' => $this->maxBandwidthKbps,
        ];
    }
}
