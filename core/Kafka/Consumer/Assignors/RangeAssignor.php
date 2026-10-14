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
 * Assigns contiguous ranges of partitions for each topic across subscribed consumers.
 */
class RangeAssignor implements PartitionAssignorInterface
{
    public function name(): string
    {
        return 'range';
    }

    public function assign(array $partitionsPerTopic, array $subscriptions): array
    {
        $assignment = [];
        foreach (array_keys($subscriptions) as $memberId) {
            $assignment[$memberId] = [];
        }

        foreach ($partitionsPerTopic as $topic => $partitions) {
            $numPartitions = count($partitions);
            if ($numPartitions === 0) {
                continue;
            }

            // Find members subscribed to this topic
            $subscribedMembers = [];
            foreach ($subscriptions as $memberId => $topics) {
                if (in_array($topic, $topics, true)) {
                    $subscribedMembers[] = $memberId;
                }
            }

            $numMembers = count($subscribedMembers);
            if ($numMembers === 0) {
                continue;
            }

            sort($subscribedMembers);

            $numPartitionsPerConsumer = (int) ($numPartitions / $numMembers);
            $consumersWithExtraPartition = $numPartitions % $numMembers;

            $assignedIdx = 0;
            for ($i = 0; $i < $numMembers; $i++) {
                $memberId = $subscribedMembers[$i];
                $length = $numPartitionsPerConsumer + ($i < $consumersWithExtraPartition ? 1 : 0);

                for ($j = 0; $j < $length; $j++) {
                    if ($assignedIdx < $numPartitions) {
                        $assignment[$memberId][] = $partitions[$assignedIdx];
                        $assignedIdx++;
                    }
                }
            }
        }

        return $assignment;
    }
}
