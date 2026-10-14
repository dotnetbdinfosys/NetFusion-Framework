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
 * Uniformly round-robins all topic partitions across group members.
 */
class RoundRobinAssignor implements PartitionAssignorInterface
{
    public function name(): string
    {
        return 'roundrobin';
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

        // Collect all partitions sorted by topic and partition
        /** @var array<TopicPartition> $allPartitions */
        $allPartitions = [];
        ksort($partitionsPerTopic);
        foreach ($partitionsPerTopic as $topic => $partitions) {
            foreach ($partitions as $tp) {
                $allPartitions[] = $tp;
            }
        }

        $memberCount = count($members);
        $memberIndex = 0;

        foreach ($allPartitions as $tp) {
            // Find next member subscribed to this topic
            $attempts = 0;
            while ($attempts < $memberCount) {
                $candidate = $members[$memberIndex % $memberCount];
                $memberIndex++;
                $attempts++;

                if (in_array($tp->topic, $subscriptions[$candidate], true)) {
                    $assignment[$candidate][] = $tp;
                    break;
                }
            }
        }

        return $assignment;
    }
}
