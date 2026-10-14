<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Synapses/SynapticConnection.php
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

use NetFusion\Core\AI\Neuromorphic\Enums\SynapsePlasticity;

/**
 * Directed Synaptic Connection between Pre- and Post-Synaptic Neurons.
 */
class SynapticConnection
{
    private float $lastPreSpikeTime = -1000.0;
    private float $lastPostSpikeTime = -1000.0;

    public function __construct(
        private readonly string $id,
        private readonly string $sourceId,
        private readonly string $targetId,
        private float $weight = 0.5,
        private readonly float $delay = 1.0, // ms axonal conduction delay
        private readonly SynapsePlasticity $plasticity = SynapsePlasticity::STATIC_WEIGHT,
        private readonly ?StdpPlasticityRule $stdpRule = null
    ) {
    }

    /**
     * Transmit pre-synaptic spike impulse, returning injected post-synaptic current.
     */
    public function transmit(float $tPre): float
    {
        $this->lastPreSpikeTime = $tPre;
        return $this->weight;
    }

    /**
     * Notify connection of a post-synaptic spike event, triggering plasticity weight adaptation.
     */
    public function onPostSpike(float $tPost): ?float
    {
        $this->lastPostSpikeTime = $tPost;

        if ($this->plasticity === SynapsePlasticity::STDP && $this->stdpRule !== null) {
            $res = $this->stdpRule->update($this->weight, $this->lastPreSpikeTime, $tPost);
            $this->weight = $res['new_weight'];
            return $res['delta_w'];
        }

        return null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): void
    {
        $this->weight = $weight;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function getPlasticity(): SynapsePlasticity
    {
        return $this->plasticity;
    }

    public function getLastPreSpikeTime(): float
    {
        return $this->lastPreSpikeTime;
    }

    public function getLastPostSpikeTime(): float
    {
        return $this->lastPostSpikeTime;
    }
}
