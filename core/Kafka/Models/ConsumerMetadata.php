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

/**
 * Metadata for a consumer group member.
 */
class ConsumerMetadata
{
    /** @var array<TopicPartition> */
    protected array $assignedPartitions = [];

    public function __construct(
        public readonly string $memberId,
        public readonly string $clientId,
        public readonly ?string $groupInstanceId = null,
        array $assignedPartitions = [],
        public int $lastHeartbeat = 0
    ) {
        $this->assignedPartitions = $assignedPartitions;
        if ($this->lastHeartbeat === 0) {
            $this->lastHeartbeat = time();
        }
    }

    /**
     * Assign a list of TopicPartitions to this consumer.
     *
     * @param array<TopicPartition> $partitions
     */
    public function assignPartitions(array $partitions): void
    {
        $this->assignedPartitions = $partitions;
    }

    /**
     * Get assigned partitions.
     *
     * @return array<TopicPartition>
     */
    public function getAssignedPartitions(): array
    {
        return $this->assignedPartitions;
    }

    /**
     * Refresh heartbeat timestamp.
     */
    public function heartbeat(?int $now = null): void
    {
        $this->lastHeartbeat = $now ?? time();
    }

    /**
     * Check if member has timed out.
     */
    public function isExpired(int $timeoutSeconds, ?int $now = null): bool
    {
        $time = $now ?? time();
        return ($time - $this->lastHeartbeat) > $timeoutSeconds;
    }

    public function toArray(): array
    {
        return [
            'memberId'           => $this->memberId,
            'clientId'           => $this->clientId,
            'groupInstanceId'    => $this->groupInstanceId,
            'assignedPartitions' => array_map(fn($tp) => $tp->toString(), $this->assignedPartitions),
            'lastHeartbeat'      => $this->lastHeartbeat,
        ];
    }
}
