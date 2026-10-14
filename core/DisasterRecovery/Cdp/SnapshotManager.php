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
 * Class SnapshotManager
 *
 * Manages full baseline database snapshots linked with CDP journal checkpoints.
 */
class SnapshotManager
{
    /**
     * In-memory or file snapshot store: snapshotId => snapshot data.
     *
     * @var array<string, array>
     */
    protected array $snapshots = [];

    /**
     * Create and record a new baseline snapshot.
     *
     * @param string $snapshotId
     * @param array $state Table-by-table array of records [ 'users' => [ id => record ] ]
     * @param float|null $timestamp
     * @param string|null $checkpointChecksum
     * @return array
     */
    public function createSnapshot(
        string $snapshotId,
        array $state,
        ?float $timestamp = null,
        ?string $checkpointChecksum = null
    ): array {
        $ts = $timestamp ?? microtime(true);
        $snapshot = [
            'id'                  => $snapshotId,
            'timestamp'           => $ts,
            'tables'              => $state,
            'checkpoint_checksum' => $checkpointChecksum,
            'record_count'        => array_sum(array_map('count', $state)),
            'created_at'          => date('Y-m-d H:i:s.u', (int) $ts),
        ];

        $this->snapshots[$snapshotId] = $snapshot;
        return $snapshot;
    }

    public function getSnapshot(string $snapshotId): ?array
    {
        return $this->snapshots[$snapshotId] ?? null;
    }

    public function hasSnapshot(string $snapshotId): bool
    {
        return isset($this->snapshots[$snapshotId]);
    }

    /**
     * Get the latest snapshot by timestamp.
     */
    public function getLatestSnapshot(): ?array
    {
        if (empty($this->snapshots)) {
            return null;
        }

        $latest = null;
        foreach ($this->snapshots as $s) {
            if ($latest === null || $s['timestamp'] > $latest['timestamp']) {
                $latest = $s;
            }
        }
        return $latest;
    }

    public function getSnapshotBefore(float $targetTimestamp): ?array
    {
        $candidate = null;
        foreach ($this->snapshots as $s) {
            if ($s['timestamp'] <= $targetTimestamp) {
                if ($candidate === null || $s['timestamp'] > $candidate['timestamp']) {
                    $candidate = $s;
                }
            }
        }
        return $candidate;
    }

    public function listSnapshots(): array
    {
        return array_values($this->snapshots);
    }

    public function deleteSnapshot(string $snapshotId): bool
    {
        if (isset($this->snapshots[$snapshotId])) {
            unset($this->snapshots[$snapshotId]);
            return true;
        }
        return false;
    }
}
