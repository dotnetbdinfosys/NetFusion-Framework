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

use NetFusion\Core\Kafka\Enums\CleanupPolicy;

/**
 * Manages log segment retention, time/size pruning, and compaction.
 */
class RetentionManager
{
    /**
     * Enforce retention policies on a partition log.
     *
     * @param PartitionLog $log The target partition log
     * @param int $retentionSeconds Max age in seconds (0 = disabled)
     * @param int $retentionBytes Max byte size (0 = disabled)
     * @param CleanupPolicy $policy Cleanup policy (DELETE or COMPACT)
     * @return int Number of pruned or compacted records
     */
    public function enforce(
        PartitionLog $log,
        int $retentionSeconds = 0,
        int $retentionBytes = 0,
        CleanupPolicy $policy = CleanupPolicy::DELETE,
        ?int $now = null
    ): int {
        if ($policy === CleanupPolicy::COMPACT) {
            return $log->compact();
        }

        $time = $now ?? time();
        $prunedCount = 0;

        // 1. Time-based retention
        if ($retentionSeconds > 0) {
            $cutoff = $time - $retentionSeconds;
            foreach ($log->getSegments() as $segment) {
                // Don't delete the active segment
                if ($segment === $log->getActiveSegment()) {
                    continue;
                }

                if ($segment->createdAt < $cutoff) {
                    $prunedCount += $log->truncateBefore($segment->getLatestOffset() + 1);
                }
            }
        }

        // 2. Size-based retention
        if ($retentionBytes > 0) {
            $totalBytes = 0;
            foreach ($log->getSegments() as $segment) {
                $totalBytes += $segment->getBytesSize();
            }

            if ($totalBytes > $retentionBytes) {
                foreach ($log->getSegments() as $segment) {
                    if ($segment === $log->getActiveSegment()) {
                        break;
                    }

                    $prunedCount += $log->truncateBefore($segment->getLatestOffset() + 1);
                    $totalBytes -= $segment->getBytesSize();

                    if ($totalBytes <= $retentionBytes) {
                        break;
                    }
                }
            }
        }

        return $prunedCount;
    }
}
