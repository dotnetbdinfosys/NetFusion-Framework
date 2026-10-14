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
 * Tracks producer PID, epoch, and monotonic sequence numbers per partition for idempotency.
 */
class IdempotentProducer
{
    /** @var array<string, int> Next sequence number per TopicPartition string */
    protected array $sequenceNumbers = [];

    public function __construct(
        protected int $producerId = 0,
        protected int $producerEpoch = 0
    ) {
        if ($this->producerId <= 0) {
            $this->producerId = (int) (hrtime(true) ^ random_int(10000, 99999));
        }
    }

    /**
     * Get the next sequence number for a topic partition and advance counter.
     */
    public function nextSequence(TopicPartition $tp): int
    {
        $key = $tp->toString();
        if (!isset($this->sequenceNumbers[$key])) {
            $this->sequenceNumbers[$key] = 0;
        }

        $seq = $this->sequenceNumbers[$key];
        $this->sequenceNumbers[$key]++;

        return $seq;
    }

    /**
     * Get current sequence number without advancing.
     */
    public function currentSequence(TopicPartition $tp): int
    {
        return $this->sequenceNumbers[$tp->toString()] ?? 0;
    }

    /**
     * Bump epoch and reset partition sequence counters.
     */
    public function bumpEpoch(): int
    {
        $this->producerEpoch++;
        $this->sequenceNumbers = [];
        return $this->producerEpoch;
    }

    public function getProducerId(): int
    {
        return $this->producerId;
    }

    public function getProducerEpoch(): int
    {
        return $this->producerEpoch;
    }
}
