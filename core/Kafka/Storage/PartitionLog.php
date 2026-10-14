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

namespace NetFusion\Core\Kafka\Storage;

use NetFusion\Core\Kafka\Enums\IsolationLevel;
use NetFusion\Core\Kafka\Exceptions\OffsetOutOfRangeException;
use NetFusion\Core\Kafka\Models\Record;
use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Append-only ordered commit log for an individual topic partition.
 */
class PartitionLog
{
    /** @var array<LogSegment> */
    protected array $segments = [];
    protected LogSegment $activeSegment;
    protected int $nextOffset = 0;
    protected int $logStartOffset = 0;

    /** @var array<int, array<int>> Offsets currently buffered in ongoing transaction [producerId => [offsets]] */
    protected array $ongoingTransactions = [];

    /** @var array<int, bool> Map of committed record offsets [offset => true] */
    protected array $committedRecordOffsets = [];

    /** @var array<int, bool> Map of aborted record offsets [offset => true] */
    protected array $abortedRecordOffsets = [];

    public function __construct(
        public readonly TopicPartition $topicPartition,
        protected readonly int $maxSegmentRecords = 50000,
        protected readonly int $maxSegmentBytes = 10485760
    ) {
        $this->activeSegment = new LogSegment($this->topicPartition, 0, $this->maxSegmentRecords, $this->maxSegmentBytes);
        $this->segments[] = $this->activeSegment;
    }

    /**
     * Append a record to the partition log.
     */
    public function append(
        string $value,
        ?string $key = null,
        array $headers = [],
        int $producerId = 0,
        int $producerEpoch = 0,
        int $sequenceNumber = 0,
        bool $isControl = false,
        ?string $controlType = null,
        ?int $timestamp = null
    ): Record {
        if ($this->activeSegment->isFull()) {
            $this->roll();
        }

        $offset = $this->nextOffset;
        $time = $timestamp ?? (int) (microtime(true) * 1000);

        $record = new Record(
            topic: $this->topicPartition->topic,
            partition: $this->topicPartition->partition,
            offset: $offset,
            key: $key,
            value: $value,
            headers: $headers,
            timestamp: $time,
            producerId: $producerId,
            producerEpoch: $producerEpoch,
            sequenceNumber: $sequenceNumber,
            isControlRecord: $isControl,
            controlType: $controlType
        );

        $this->activeSegment->append($record);
        $this->nextOffset++;

        // Track transactional record offsets and commit/abort markers
        if ($producerId > 0) {
            if ($isControl) {
                if ($record->isTransactionCommit()) {
                    foreach ($this->ongoingTransactions[$producerId] ?? [] as $off) {
                        $this->committedRecordOffsets[$off] = true;
                    }
                    $this->ongoingTransactions[$producerId] = [];
                } elseif ($record->isTransactionAbort()) {
                    foreach ($this->ongoingTransactions[$producerId] ?? [] as $off) {
                        $this->abortedRecordOffsets[$off] = true;
                    }
                    $this->ongoingTransactions[$producerId] = [];
                }
            } else {
                $this->ongoingTransactions[$producerId][] = $offset;
            }
        }

        return $record;
    }

    /**
     * Read records starting from offset up to maxRecords.
     *
     * @return array<Record>
     */
    public function read(
        int $fromOffset,
        int $maxRecords = 100,
        IsolationLevel $isolation = IsolationLevel::READ_UNCOMMITTED
    ): array {
        if ($fromOffset < $this->logStartOffset) {
            throw new OffsetOutOfRangeException(
                $this->topicPartition->topic,
                $this->topicPartition->partition,
                $fromOffset,
                $this->logStartOffset,
                $this->nextOffset
            );
        }

        if ($fromOffset > $this->nextOffset) {
            throw new OffsetOutOfRangeException(
                $this->topicPartition->topic,
                $this->topicPartition->partition,
                $fromOffset,
                $this->logStartOffset,
                $this->nextOffset
            );
        }

        if ($fromOffset === $this->nextOffset) {
            return [];
        }

        $results = [];
        $collected = 0;

        foreach ($this->segments as $segment) {
            if ($segment->getLatestOffset() < $fromOffset) {
                continue;
            }

            $records = $segment->read($fromOffset, $maxRecords - $collected);
            foreach ($records as $record) {
                // Skip internal transaction control markers for consumers
                if ($record->isControlRecord) {
                    continue;
                }

                // If READ_COMMITTED isolation level, filter out uncommitted transactional records
                if ($isolation === IsolationLevel::READ_COMMITTED && $record->producerId > 0) {
                    $isCommitted = isset($this->committedRecordOffsets[$record->offset]);
                    if (!$isCommitted) {
                        continue;
                    }
                }

                $results[] = $record;
                $collected++;
                if ($collected >= $maxRecords) {
                    break 2;
                }
            }
        }

        return $results;
    }

    /**
     * Roll over to a new active log segment.
     */
    public function roll(): LogSegment
    {
        $this->activeSegment = new LogSegment(
            $this->topicPartition,
            $this->nextOffset,
            $this->maxSegmentRecords,
            $this->maxSegmentBytes
        );
        $this->segments[] = $this->activeSegment;
        return $this->activeSegment;
    }

    /**
     * Compact partition log, deduplicating messages with identical keys.
     *
     * @return int Number of duplicate/tombstone records pruned
     */
    public function compact(): int
    {
        $latestPerKey = [];
        $totalOriginal = 0;

        foreach ($this->segments as $segment) {
            foreach ($segment->getRecords() as $record) {
                $totalOriginal++;
                if ($record->key !== null) {
                    $latestPerKey[$record->key] = $record;
                }
            }
        }

        // Rebuild segments with only latest retained records
        $retainedRecords = [];
        foreach ($this->segments as $segment) {
            foreach ($segment->getRecords() as $record) {
                if ($record->key === null) {
                    // Non-keyed records are preserved in order
                    $retainedRecords[] = $record;
                } elseif (isset($latestPerKey[$record->key]) && $latestPerKey[$record->key]->offset === $record->offset) {
                    // If latest value is empty string or tombstone, optionally discard or retain
                    if ($record->value !== '') {
                        $retainedRecords[] = $record;
                    }
                }
            }
        }

        // Reset segments and repopulate
        $this->segments = [];
        $baseOffset = !empty($retainedRecords) ? $retainedRecords[0]->offset : 0;
        $this->activeSegment = new LogSegment($this->topicPartition, $baseOffset, $this->maxSegmentRecords, $this->maxSegmentBytes);
        $this->segments[] = $this->activeSegment;

        foreach ($retainedRecords as $rec) {
            if ($this->activeSegment->isFull()) {
                $this->roll();
            }
            $this->activeSegment->append($rec);
        }

        $pruned = $totalOriginal - count($retainedRecords);
        return max(0, $pruned);
    }

    /**
     * Truncate the log removing all records at or above targetOffset.
     */
    public function truncateTo(int $targetOffset): void
    {
        $retainedSegments = [];
        foreach ($this->segments as $segment) {
            if ($segment->baseOffset >= $targetOffset) {
                continue;
            }

            $segment->truncateTo($targetOffset);
            $retainedSegments[] = $segment;
        }

        $this->segments = !empty($retainedSegments) ? $retainedSegments : [
            new LogSegment($this->topicPartition, $targetOffset, $this->maxSegmentRecords, $this->maxSegmentBytes)
        ];
        $this->activeSegment = end($this->segments);
        $this->nextOffset = $targetOffset;
    }

    /**
     * Discard segments whose latest offset is strictly less than targetOffset.
     */
    public function truncateBefore(int $targetOffset): int
    {
        $discarded = 0;
        $retained = [];

        foreach ($this->segments as $segment) {
            // Keep active segment regardless
            if ($segment === $this->activeSegment || $segment->getLatestOffset() >= $targetOffset) {
                $retained[] = $segment;
            } else {
                $discarded += $segment->count();
            }
        }

        $this->segments = $retained;
        $this->logStartOffset = max($this->logStartOffset, $targetOffset);

        return $discarded;
    }

    public function getLogEndOffset(): int
    {
        return $this->nextOffset;
    }

    public function getLogStartOffset(): int
    {
        return $this->logStartOffset;
    }

    public function getHighWatermark(): int
    {
        return $this->nextOffset;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getActiveSegment(): LogSegment
    {
        return $this->activeSegment;
    }

    public function totalRecords(): int
    {
        $count = 0;
        foreach ($this->segments as $segment) {
            $count += $segment->count();
        }
        return $count;
    }
}
