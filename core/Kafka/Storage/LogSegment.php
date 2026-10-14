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

use NetFusion\Core\Kafka\Models\Record;
use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Represents an individual log segment chunk holding an ordered sequence of records.
 */
class LogSegment
{
    /** @var array<int, Record> Indexed by offset */
    protected array $records = [];

    protected OffsetIndex $index;
    protected int $bytesSize = 0;
    protected int $latestOffset = -1;
    public readonly int $createdAt;

    public function __construct(
        public readonly TopicPartition $topicPartition,
        public readonly int $baseOffset,
        public readonly int $maxRecords = 50000,
        public readonly int $maxBytes = 10485760 // 10 MB
    ) {
        $this->index = new OffsetIndex();
        $this->createdAt = time();
        $this->latestOffset = $baseOffset - 1;
    }

    /**
     * Append a record to this segment.
     */
    public function append(Record $record): void
    {
        $offset = $record->offset;
        $position = count($this->records);

        $this->records[$offset] = $record;
        $this->index->append($offset, $position);

        $this->bytesSize += $record->size();
        $this->latestOffset = $offset;
    }

    /**
     * Read records starting from a given offset up to maxRecords.
     *
     * @return array<Record>
     */
    public function read(int $fromOffset, int $maxRecords = 100): array
    {
        $results = [];
        $count = 0;

        foreach ($this->records as $offset => $record) {
            if ($offset >= $fromOffset) {
                $results[] = $record;
                $count++;
                if ($count >= $maxRecords) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Retrieve single record by offset.
     */
    public function getRecord(int $offset): ?Record
    {
        return $this->records[$offset] ?? null;
    }

    /**
     * Check if segment has reached capacity.
     */
    public function isFull(): bool
    {
        return count($this->records) >= $this->maxRecords || $this->bytesSize >= $this->maxBytes;
    }

    /**
     * Get highest record offset in segment.
     */
    public function getLatestOffset(): int
    {
        return $this->latestOffset;
    }

    /**
     * Get total number of records in this segment.
     */
    public function count(): int
    {
        return count($this->records);
    }

    /**
     * Get total byte size of records in this segment.
     */
    public function getBytesSize(): int
    {
        return $this->bytesSize;
    }

    /**
     * Truncate segment removing records at or after given offset.
     */
    public function truncateTo(int $targetOffset): void
    {
        foreach (array_keys($this->records) as $offset) {
            if ($offset >= $targetOffset) {
                $this->bytesSize -= $this->records[$offset]->size();
                unset($this->records[$offset]);
            }
        }

        $this->index->truncateTo($targetOffset);
        $this->latestOffset = empty($this->records) ? ($this->baseOffset - 1) : max(array_keys($this->records));
    }

    /**
     * Get all records in segment.
     *
     * @return array<int, Record>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Access segment index.
     */
    public function getIndex(): OffsetIndex
    {
        return $this->index;
    }
}
