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

namespace NetFusion\Core\Kafka\Producer;

use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Micro-batching accumulator buffering outgoing producer records per TopicPartition.
 */
class RecordAccumulator
{
    /** @var array<string, array<array>> Batches indexed by "topic-partition" */
    protected array $batches = [];
    protected int $totalRecords = 0;

    public function __construct(
        protected readonly int $batchSize = 16384, // 16 KB default
        protected readonly int $lingerMs = 0
    ) {
    }

    /**
     * Append a pending record to the target partition batch.
     */
    public function append(TopicPartition $tp, array $recordData): void
    {
        $key = $tp->toString();
        $this->batches[$key][] = $recordData;
        $this->totalRecords++;
    }

    /**
     * Drain and retrieve all buffered batches, clearing the accumulator.
     *
     * @return array<string, array<array>>
     */
    public function drain(): array
    {
        $drained = $this->batches;
        $this->batches = [];
        $this->totalRecords = 0;
        return $drained;
    }

    /**
     * Get total buffered records.
     */
    public function count(): int
    {
        return $this->totalRecords;
    }

    /**
     * Check if accumulator has pending records.
     */
    public function hasRecords(): bool
    {
        return $this->totalRecords > 0;
    }

    /**
     * Clear all pending buffers.
     */
    public function clear(): void
    {
        $this->batches = [];
        $this->totalRecords = 0;
    }
}
