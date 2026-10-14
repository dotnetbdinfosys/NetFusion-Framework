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

namespace NetFusion\Core\Kafka\Consumer\Assignors;

use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Minimizes partition movements across rebalances while ensuring balanced workload.
 */
class StickyAssignor implements PartitionAssignorInterface
{
    /** @var array<string, array<string>> Previous assignments [memberId => ["tp-str"]] */
    protected array $previousAssignments = [];

    public function name(): string
    {
        return 'sticky';
    }

    public function assign(array $partitionsPerTopic, array $subscriptions): array
    {
        $assignment = [];
        $members = array_keys($subscriptions);
        sort($members);

        foreach ($members as $memberId) {
            $assignment[$memberId] = [];
        }

        if (empty($members)) {
            return $assignment;
        }

        // 1. Gather all partitions
        /** @var array<string, TopicPartition> $allPartitions */
        $allPartitions = [];
        foreach ($partitionsPerTopic as $topic => $partitions) {
            foreach ($partitions as $tp) {
                $allPartitions[$tp->toString()] = $tp;
            }
        }

        $assignedPartitions = [];

        // 2. Retain sticky previous assignments if member still exists and subscribed
        foreach ($this->previousAssignments as $oldMember => $oldTpStrings) {
            if (!isset($subscriptions[$oldMember])) {
                continue;
            }

            foreach ($oldTpStrings as $tpStr) {
                if (isset($allPartitions[$tpStr]) && !isset($assignedPartitions[$tpStr])) {
                    $tp = $allPartitions[$tpStr];
                    if (in_array($tp->topic, $subscriptions[$oldMember], true)) {
                        $assignment[$oldMember][] = $tp;
                        $assignedPartitions[$tpStr] = true;
                    }
                }
            }
        }

        // 3. Assign unassigned partitions to members with fewest partitions
        foreach ($allPartitions as $tpStr => $tp) {
            if (isset($assignedPartitions[$tpStr])) {
                continue;
            }

            // Find eligible member with fewest assigned partitions
            $bestMember = null;
            $minCount = PHP_INT_MAX;

            foreach ($members as $m) {
                if (in_array($tp->topic, $subscriptions[$m], true)) {
                    $count = count($assignment[$m]);
                    if ($count < $minCount) {
                        $minCount = $count;
                        $bestMember = $m;
                    }
                }
            }

            if ($bestMember !== null) {
                $assignment[$bestMember][] = $tp;
                $assignedPartitions[$tpStr] = true;
            }
        }

        // Update state
        $this->previousAssignments = [];
        foreach ($assignment as $m => $tps) {
            $this->previousAssignments[$m] = array_map(fn(TopicPartition $p) => $p->toString(), $tps);
        }

        return $assignment;
    }
}
