<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Mesh/SpikeEvent.php
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

namespace NetFusion\Core\AI\Neuromorphic\Mesh;

/**
 * Discrete Asynchronous Spike Event in the Temporal Mesh.
 */
class SpikeEvent
{
    public function __construct(
        private readonly string $sourceNeuronId,
        private readonly string $targetNeuronId,
        private readonly float $deliveryTime,
        private readonly float $weight = 1.0,
        /** @var array<string, mixed> */
        private readonly array $payload = []
    ) {
    }

    public function getSourceNeuronId(): string
    {
        return $this->sourceNeuronId;
    }

    public function getTargetNeuronId(): string
    {
        return $this->targetNeuronId;
    }

    public function getDeliveryTime(): float
    {
        return $this->deliveryTime;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
