<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Neurons/IzhikevichNeuron.php
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
 * Izhikevich Dynamical Polychronous Neuron Model.
 *
 * Implements two-dimensional system of ordinary differential equations:
 *   dv/dt = 0.04*v^2 + 5*v + 140 - u + I
 *   du/dt = a*(b*v - u)
 * With auxiliary after-spike resetting:
 *   if v >= 30 mV, then v <- c, u <- u + d
 */
class IzhikevichNeuron
{
    private float $v;
    private float $u;
    private float $lastSpikeTime = -1000.0;
    /** @var float[] */
    private array $spikeHistory = [];

    public function __construct(
        private readonly string $id,
        private readonly float $a = 0.02,   // Time scale of recovery variable u
        private readonly float $b = 0.2,    // Sensitivity of recovery variable u
        private readonly float $c = -65.0,  // After-spike reset value of membrane potential v (mV)
        private readonly float $d = 8.0,    // After-spike reset of recovery variable u
        private readonly float $vPeak = 30.0 // Peak action potential spike threshold (mV)
    ) {
        $this->v = $this->c;
        $this->u = $this->b * $this->v;
    }

    /**
     * Advance Izhikevich dynamical system by dt using numerical Euler integration.
     *
     * @param float $inputCurrent Injected synaptic current
     * @param float $dt Timestep in ms
     * @param float $currentTime Simulation timestamp
     * @return bool True if action potential spike reached peak
     */
    public function step(float $inputCurrent, float $dt = 1.0, float $currentTime = 0.0): bool
    {
        // Half-step sub-stepping for numerical stability
        $subSteps = 2;
        $h = $dt / $subSteps;
        $fired = false;

        for ($s = 0; $s < $subSteps; $s++) {
            $dv = (0.04 * ($this->v ** 2) + 5.0 * $this->v + 140.0 - $this->u + $inputCurrent) * $h;
            $du = ($this->a * ($this->b * $this->v - $this->u)) * $h;

            $this->v += $dv;
            $this->u += $du;

            if ($this->v >= $this->vPeak) {
                $fired = true;
                $this->v = $this->c;
                $this->u += $this->d;
                $this->lastSpikeTime = $currentTime;
                $this->spikeHistory[] = $currentTime;
            }
        }

        return $fired;
    }

    public function reset(): void
    {
        $this->v = $this->c;
        $this->u = $this->b * $this->v;
        $this->lastSpikeTime = -1000.0;
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

    public function getRecovery(): float
    {
        return $this->u;
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
}
