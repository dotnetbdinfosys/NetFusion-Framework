<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Mesh/TemporalSpikeQueue.php
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

use NetFusion\Core\AI\Neuromorphic\Exceptions\SpikeBufferOverflowException;

/**
 * Chronologically Ordered Asynchronous Spike Event Queue.
 *
 * Implements discrete-event priority scheduling for millisecond-precise axonal transmission.
 */
class TemporalSpikeQueue
{
    /** @var SpikeEvent[] */
    private array $queue = [];

    public function __construct(
        private readonly int $maxCapacity = 100000
    ) {
    }

    /**
     * Push a spike event into the chronological queue.
     *
     * @throws SpikeBufferOverflowException
     */
    public function push(SpikeEvent $event): void
    {
        if (count($this->queue) >= $this->maxCapacity) {
            throw new SpikeBufferOverflowException(
                "Temporal spike buffer capacity of {$this->maxCapacity} exceeded."
            );
        }

        $this->queue[] = $event;

        // Maintain chronological order by deliveryTime
        usort($this->queue, fn(SpikeEvent $a, SpikeEvent $b) => $a->getDeliveryTime() <=> $b->getDeliveryTime());
    }

    /**
     * Extract all spikes scheduled for delivery up to and including $currentTime.
     *
     * @return SpikeEvent[]
     */
    public function popDue(float $currentTime): array
    {
        $due = [];
        $remaining = [];

        foreach ($this->queue as $event) {
            if ($event->getDeliveryTime() <= $currentTime + 1e-6) {
                $due[] = $event;
            } else {
                $remaining[] = $event;
            }
        }

        $this->queue = $remaining;
        return $due;
    }

    public function peekNextTime(): ?float
    {
        return !empty($this->queue) ? $this->queue[0]->getDeliveryTime() : null;
    }

    public function count(): int
    {
        return count($this->queue);
    }

    public function isEmpty(): bool
    {
        return empty($this->queue);
    }

    public function clear(): void
    {
        $this->queue = [];
    }
}
