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
 * Class CdpEngine
 *
 * Central engine for Continuous Data Protection (CDP) and Point-in-Time Recovery (PITR).
 */
class CdpEngine
{
    protected MutationJournal $journal;
    protected SnapshotManager $snapshots;
    protected string $currentRegion = 'us-east-1';

    public function __construct(
        ?MutationJournal $journal = null,
        ?SnapshotManager $snapshots = null,
        string $region = 'us-east-1'
    ) {
        $this->journal = $journal ?? new MutationJournal();
        $this->snapshots = $snapshots ?? new SnapshotManager();
        $this->currentRegion = $region;
    }

    public function getJournal(): MutationJournal
    {
        return $this->journal;
    }

    public function getSnapshots(): SnapshotManager
    {
        return $this->snapshots;
    }

    public function getRegion(): string
    {
        return $this->currentRegion;
    }

    public function setRegion(string $region): self
    {
        $this->currentRegion = $region;
        return $this;
    }

    /**
     * Record an INSERT mutation.
     */
    public function recordInsert(
        string $table,
        mixed $primaryKey,
        array $data,
        ?string $txId = null,
        ?float $timestamp = null
    ): MutationRecord {
        return $this->journal->record(
            txId: $txId ?? $this->generateTxId(),
            table: $table,
            operation: MutationRecord::OP_INSERT,
            primaryKey: $primaryKey,
            beforeState: null,
            afterState: $data,
            timestamp: $timestamp,
            region: $this->currentRegion
        );
    }

    /**
     * Record an UPDATE mutation.
     */
    public function recordUpdate(
        string $table,
        mixed $primaryKey,
        array $before,
        array $after,
        ?string $txId = null,
        ?float $timestamp = null
    ): MutationRecord {
        return $this->journal->record(
            txId: $txId ?? $this->generateTxId(),
            table: $table,
            operation: MutationRecord::OP_UPDATE,
            primaryKey: $primaryKey,
            beforeState: $before,
            afterState: $after,
            timestamp: $timestamp,
            region: $this->currentRegion
        );
    }

    /**
     * Record a DELETE mutation.
     */
    public function recordDelete(
        string $table,
        mixed $primaryKey,
        array $before,
        ?string $txId = null,
        ?float $timestamp = null
    ): MutationRecord {
        return $this->journal->record(
            txId: $txId ?? $this->generateTxId(),
            table: $table,
            operation: MutationRecord::OP_DELETE,
            primaryKey: $primaryKey,
            beforeState: $before,
            afterState: null,
            timestamp: $timestamp,
            region: $this->currentRegion
        );
    }

    /**
     * Point-in-Time Recovery (PITR): Reconstitute table state to an exact historical microsecond.
     *
     * @param float $targetTimestamp
     * @param string|null $baselineSnapshotId Optional specific snapshot to start from
     * @return array<string, array<string|int, array>> Reconstituted table data
     */
    public function restoreToTimestamp(float $targetTimestamp, ?string $baselineSnapshotId = null): array
    {
        $state = [];
        $fromTime = 0.0;

        // 1. Locate baseline snapshot before target time
        $snapshot = $baselineSnapshotId !== null
            ? $this->snapshots->getSnapshot($baselineSnapshotId)
            : $this->snapshots->getSnapshotBefore($targetTimestamp);

        if ($snapshot !== null) {
            $state = $snapshot['tables'];
            $fromTime = (float) $snapshot['timestamp'];
        }

        // 2. Fetch all mutations between snapshot and target timestamp
        $mutations = $this->journal->getRecordsBetween($fromTime, $targetTimestamp);

        // 3. Apply mutations forward
        foreach ($mutations as $m) {
            $this->applyMutationToState($state, $m);
        }

        return $state;
    }

    /**
     * Replay mutations up to a specific transaction ID.
     */
    public function restoreToTx(string $txId, ?string $baselineSnapshotId = null): array
    {
        $state = [];
        $fromTime = 0.0;

        $snapshot = $baselineSnapshotId !== null
            ? $this->snapshots->getSnapshot($baselineSnapshotId)
            : $this->snapshots->getLatestSnapshot();

        if ($snapshot !== null) {
            $state = $snapshot['tables'];
            $fromTime = (float) $snapshot['timestamp'];
        }

        $allMutations = $this->journal->getRecordsSince($fromTime);
        $found = false;

        foreach ($allMutations as $m) {
            $this->applyMutationToState($state, $m);
            if ($m->txId === $txId) {
                $found = true;
                break;
            }
        }

        if (!$found && !empty($allMutations)) {
            throw new RuntimeException("Transaction ID '{$txId}' not found in journal stream");
        }

        return $state;
    }

    /**
     * Apply a single mutation record to the in-memory state table array.
     */
    protected function applyMutationToState(array &$state, MutationRecord $m): void
    {
        $table = $m->table;
        $pk = is_scalar($m->primaryKey) ? (string) $m->primaryKey : json_encode($m->primaryKey);

        if (!isset($state[$table])) {
            $state[$table] = [];
        }

        switch ($m->operation) {
            case MutationRecord::OP_INSERT:
            case MutationRecord::OP_UPDATE:
                $state[$table][$pk] = $m->afterState ?? [];
                break;
            case MutationRecord::OP_DELETE:
                unset($state[$table][$pk]);
                break;
        }
    }

    /**
     * Measure current Recovery Point Objective (RPO) lag in seconds.
     * RPO = difference between now and the timestamp of the latest replicated mutation.
     */
    public function measureRpo(?float $now = null): float
    {
        $currentTime = $now ?? microtime(true);
        $records = $this->journal->getRecords();

        if (empty($records)) {
            return 0.0;
        }

        $latestRecord = end($records);
        $lag = $currentTime - $latestRecord->timestamp;
        return max(0.0, round($lag, 4));
    }

    protected function generateTxId(): string
    {
        return 'tx_' . bin2hex(random_bytes(8));
    }
}
