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
 * Strategy interface for assigning partitions among consumer group members during rebalance.
 */
interface PartitionAssignorInterface
{
    /**
     * Compute partition assignment.
     *
     * @param array<string, array<TopicPartition>> $partitionsPerTopic Topic name => array of TopicPartitions
     * @param array<string, array<string>> $subscriptions Member ID => array of subscribed topics
     * @return array<string, array<TopicPartition>> Member ID => array of assigned TopicPartitions
     */
    public function assign(array $partitionsPerTopic, array $subscriptions): array;

    /**
     * Protocol name identifying this assignor.
     */
    public function name(): string;
}
