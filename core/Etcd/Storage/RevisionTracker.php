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

namespace NetFusion\Core\Etcd\Storage;

/**
 * Manages monotonic revision numbers and compaction watermark for the MVCC store.
 */
class RevisionTracker
{
    protected int $currentRevision = 1;
    protected int $compactRevision = 0;

    public function __construct(int $initialRevision = 1, int $compactRevision = 0)
    {
        $this->currentRevision = max(1, $initialRevision);
        $this->compactRevision = max(0, $compactRevision);
    }

    /**
     * Get the current global revision.
     */
    public function current(): int
    {
        return $this->currentRevision;
    }

    /**
     * Atomically increment and return the next revision number.
     */
    public function next(): int
    {
        $this->currentRevision++;
        return $this->currentRevision;
    }

    /**
     * Set the current global revision (e.g. during recovery or migration).
     */
    public function setRevision(int $revision): void
    {
        $this->currentRevision = max($this->currentRevision, $revision);
    }

    /**
     * Advance the compaction watermark revision.
     */
    public function compact(int $revision): int
    {
        if ($revision > $this->currentRevision) {
            $revision = $this->currentRevision;
        }

        if ($revision > $this->compactRevision) {
            $this->compactRevision = $revision;
        }

        return $this->compactRevision;
    }

    /**
     * Get the current compact watermark revision.
     */
    public function compactRevision(): int
    {
        return $this->compactRevision;
    }

    /**
     * Check if a given revision has been compacted (discarded).
     */
    public function isCompacted(int $revision): bool
    {
        return $revision > 0 && $revision < $this->compactRevision;
    }
}
