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

namespace NetFusion\Core\Kafka\Models;

/**
 * Represents an individual message/record in a Kafka partition log.
 */
class Record
{
    public function __construct(
        public readonly string $topic,
        public readonly int $partition,
        public readonly int $offset,
        public readonly ?string $key,
        public readonly string $value,
        public readonly array $headers = [],
        public readonly int $timestamp = 0,
        public readonly int $producerId = 0,
        public readonly int $producerEpoch = 0,
        public readonly int $sequenceNumber = 0,
        public readonly bool $isControlRecord = false,
        public readonly ?string $controlType = null
    ) {
    }

    /**
     * Get the TopicPartition instance for this record.
     */
    public function topicPartition(): TopicPartition
    {
        return new TopicPartition($this->topic, $this->partition);
    }

    /**
     * Check if this is a transaction commit marker.
     */
    public function isTransactionCommit(): bool
    {
        return $this->isControlRecord && strtoupper((string) $this->controlType) === 'COMMIT';
    }

    /**
     * Check if this is a transaction abort marker.
     */
    public function isTransactionAbort(): bool
    {
        return $this->isControlRecord && strtoupper((string) $this->controlType) === 'ABORT';
    }

    /**
     * Approximate record serialized size in bytes.
     */
    public function size(): int
    {
        $headersSize = 0;
        foreach ($this->headers as $k => $v) {
            $headersSize += strlen((string) $k) + strlen((string) $v);
        }

        return strlen((string) $this->key) + strlen($this->value) + $headersSize + 64;
    }

    /**
     * Export record to array format.
     */
    public function toArray(): array
    {
        return [
            'topic'           => $this->topic,
            'partition'       => $this->partition,
            'offset'          => $this->offset,
            'key'             => $this->key,
            'value'           => $this->value,
            'headers'         => $this->headers,
            'timestamp'       => $this->timestamp,
            'producerId'      => $this->producerId,
            'producerEpoch'   => $this->producerEpoch,
            'sequenceNumber'  => $this->sequenceNumber,
            'isControlRecord' => $this->isControlRecord,
            'controlType'     => $this->controlType,
        ];
    }
}
