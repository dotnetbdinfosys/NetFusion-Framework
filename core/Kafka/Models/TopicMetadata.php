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

namespace NetFusion\Core\Kafka\Models;

use NetFusion\Core\Kafka\Enums\CleanupPolicy;

/**
 * Metadata and configuration for a Kafka topic.
 */
class TopicMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly int $partitionsCount = 1,
        public readonly int $replicationFactor = 1,
        public readonly array $config = []
    ) {
    }

    /**
     * Get list of partition IDs [0, 1, ..., N-1].
     *
     * @return array<int>
     */
    public function partitions(): array
    {
        return range(0, max(0, $this->partitionsCount - 1));
    }

    /**
     * Get all TopicPartition objects for this topic.
     *
     * @return array<TopicPartition>
     */
    public function topicPartitions(): array
    {
        $list = [];
        for ($p = 0; $p < $this->partitionsCount; $p++) {
            $list[] = new TopicPartition($this->name, $p);
        }
        return $list;
    }

    /**
     * Get cleanup policy for this topic.
     */
    public function cleanupPolicy(): CleanupPolicy
    {
        $policy = $this->config['cleanup.policy'] ?? 'delete';
        return CleanupPolicy::tryFrom(strtolower($policy)) ?? CleanupPolicy::DELETE;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'name'              => $this->name,
            'partitionsCount'   => $this->partitionsCount,
            'replicationFactor' => $this->replicationFactor,
            'config'            => $this->config,
        ];
    }
}
