<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Neurons/LIFNeuron.php
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

namespace NetFusion\Core\AI\Neuromorphic\Neurons;

/**
 * Standard Leaky Integrate-and-Fire (LIF) Biophysical Neuron Model.
 *
 * Simulates differential subthreshold membrane potential integration:
 *   tau_m * (dV/dt) = -(V - V_rest) + R_m * I(t)
 * With instantaneous threshold spike generation and refractory hyperpolarization reset.
 */
class LIFNeuron
{
    private float $v;
    private float $lastSpikeTime = -1000.0;
    private float $refractoryRemaining = 0.0;
    /** @var float[] */
    private array $spikeHistory = [];

    public function __construct(
        private readonly string $id,
        private readonly float $tauMembrane = 20.0,       // ms
        private readonly float $vRest = -70.0,            // mV
        private readonly float $vReset = -75.0,           // mV
        private readonly float $vThreshold = -55.0,       // mV
        private readonly float $refractoryPeriod = 2.0,   // ms
        private readonly float $resistance = 10.0         // MOhm
    ) {
        $this->v = $this->vRest;
    }

    /**
     * Advance simulation by dt (ms) with incoming synaptic input current (nA).
     *
     * @param float $inputCurrent Synaptic injection current
     * @param float $dt Timestep delta in milliseconds
     * @param float $currentTime Simulation timestamp
     * @return bool True if an action potential spike was generated
     */
    public function step(float $inputCurrent, float $dt = 1.0, float $currentTime = 0.0): bool
    {
        if ($this->refractoryRemaining > 0.0) {
            $this->refractoryRemaining = max(0.0, $this->refractoryRemaining - $dt);
            $this->v = $this->vReset;
            return false;
        }

        // Numerical Euler integration of leaky integrate differential
        $leak = -($this->v - $this->vRest);
        $injection = $this->resistance * $inputCurrent;
        $dv = ($leak + $injection) / max(0.001, $this->tauMembrane) * $dt;

        $this->v += $dv;

        // Threshold spike evaluation
        if ($this->v >= $this->vThreshold) {
            $this->v = $this->vReset;
            $this->refractoryRemaining = $this->refractoryPeriod;
            $this->lastSpikeTime = $currentTime;
            $this->spikeHistory[] = $currentTime;
            return true;
        }

        return false;
    }

    public function reset(): void
    {
        $this->v = $this->vRest;
        $this->lastSpikeTime = -1000.0;
        $this->refractoryRemaining = 0.0;
        $this->spikeHistory = [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPotential(): float
    {
        return $this->v;
    }

    public function getLastSpikeTime(): float
    {
        return $this->lastSpikeTime;
    }

    public function getSpikeCount(): int
    {
        return count($this->spikeHistory);
    }

    /**
     * @return float[]
     */
    public function getSpikeHistory(): array
    {
        return $this->spikeHistory;
    }

    public function getVRest(): float
    {
        return $this->vRest;
    }

    public function getVThreshold(): float
    {
        return $this->vThreshold;
    }

    public function getVReset(): float
    {
        return $this->vReset;
    }
}
