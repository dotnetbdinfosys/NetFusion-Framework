<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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

namespace NetFusion\Core\DisasterRecovery\Cdp;

use RuntimeException;

/**
 * Class MutationJournal
 *
 * Cryptographically chained append-only journal store for CDP mutations.
 */
class MutationJournal
{
    /**
     * @var MutationRecord[]
     */
    protected array $records = [];

    protected ?string $latestChecksum = null;

    public function __construct()
    {
    }

    /**
     * Record a new mutation into the journal.
     */
    public function record(
        string $txId,
        string $table,
        string $operation,
        mixed $primaryKey,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?float $timestamp = null,
        string $region = 'primary'
    ): MutationRecord {
        $ts = $timestamp ?? microtime(true);
        $record = new MutationRecord(
            txId: $txId,
            table: $table,
            operation: $operation,
            primaryKey: $primaryKey,
            beforeState: $beforeState,
            afterState: $afterState,
            timestamp: $ts,
            region: $region,
            previousChecksum: $this->latestChecksum
        );

        $this->append($record);
        return $record;
    }

    /**
     * Append an existing MutationRecord.
     */
    public function append(MutationRecord $record): void
    {
        $this->records[] = $record;
        $this->latestChecksum = $record->checksum;
    }

    /**
     * Get all journal records.
     *
     * @return MutationRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function getLatestChecksum(): ?string
    {
        return $this->latestChecksum;
    }

    /**
     * Get records since a specific timestamp.
     *
     * @return MutationRecord[]
     */
    public function getRecordsSince(float $timestamp): array
    {
        $filtered = [];
        foreach ($this->records as $r) {
            if ($r->timestamp >= $timestamp) {
                $filtered[] = $r;
            }
        }
        return $filtered;
    }

    /**
     * Get records in a timestamp window [from, to].
     *
     * @return MutationRecord[]
     */
    public function getRecordsBetween(float $from, float $to): array
    {
        $filtered = [];
        foreach ($this->records as $r) {
            if ($r->timestamp >= $from && $r->timestamp <= $to) {
                $filtered[] = $r;
            }
        }
        return $filtered;
    }

    /**
     * Verify the cryptographic hash chain from beginning to end.
     */
    public function verifyChain(): bool
    {
        $prev = null;
        foreach ($this->records as $r) {
            if (!$r->verifyIntegrity($prev)) {
                return false;
            }
            $prev = $r->checksum;
        }
        return true;
    }

    /**
     * Export delta stream since a given timestamp for cross-region replication.
     */
    public function exportDelta(float $sinceTimestamp = 0.0): string
    {
        $records = $this->getRecordsSince($sinceTimestamp);
        $data = array_map(fn(MutationRecord $r) => $r->toArray(), $records);
        return json_encode([
            'count'           => count($data),
            'latest_checksum' => $this->latestChecksum,
            'records'         => $data,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Import and apply incoming delta stream from another region.
     */
    public function importDelta(string $deltaJson): int
    {
        $payload = json_decode($deltaJson, true);
        if (!is_array($payload) || !isset($payload['records'])) {
            throw new RuntimeException("Invalid CDP delta stream payload");
        }

        $applied = 0;
        foreach ($payload['records'] as $recordData) {
            $record = MutationRecord::fromArray($recordData);
            // Check if already present by txId
            if ($this->hasTx($record->txId)) {
                continue;
            }
            $this->append($record);
            $applied++;
        }

        return $applied;
    }

    public function hasTx(string $txId): bool
    {
        foreach ($this->records as $r) {
            if ($r->txId === $txId) {
                return true;
            }
        }
        return false;
    }

    public function clear(): void
    {
        $this->records = [];
        $this->latestChecksum = null;
    }
}
