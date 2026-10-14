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

namespace NetFusion\Core\Kafka\Consumer;

use NetFusion\Core\Kafka\Consumer\Assignors\PartitionAssignorInterface;
use NetFusion\Core\Kafka\Models\OffsetAndMetadata;
use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Coordinates consumer groups, joins, syncs, heartbeats, and offset persistence.
 */
class GroupCoordinator
{
    /** @var array<string, ConsumerGroup> Group ID => ConsumerGroup */
    protected array $groups = [];

    /**
     * Get or create a consumer group.
     */
    public function getOrCreateGroup(string $groupId, ?PartitionAssignorInterface $assignor = null): ConsumerGroup
    {
        if (!isset($this->groups[$groupId])) {
            $this->groups[$groupId] = new ConsumerGroup($groupId, $assignor);
        }
        return $this->groups[$groupId];
    }

    /**
     * Retrieve consumer group if exists.
     */
    public function getGroup(string $groupId): ?ConsumerGroup
    {
        return $this->groups[$groupId] ?? null;
    }

    /**
     * Join group and compute partition assignments.
     *
     * @param array<string> $topics
     * @param array<string, array<TopicPartition>> $partitionsPerTopic
     * @return array<TopicPartition> Assigned partitions for this member
     */
    public function joinGroup(
        string $groupId,
        string $memberId,
        string $clientId,
        array $topics,
        array $partitionsPerTopic
    ): array {
        $group = $this->getOrCreateGroup($groupId);
        $group->addMember($memberId, $clientId, $topics);

        $assignments = $group->rebalance($partitionsPerTopic);

        return $assignments[$memberId] ?? [];
    }

    /**
     * Leave group and trigger rebalance among remaining members.
     *
     * @param array<string, array<TopicPartition>> $partitionsPerTopic
     */
    public function leaveGroup(string $groupId, string $memberId, array $partitionsPerTopic): void
    {
        $group = $this->getGroup($groupId);
        if ($group === null) {
            return;
        }

        $group->removeMember($memberId);
        if (!empty($group->getMembers())) {
            $group->rebalance($partitionsPerTopic);
        }
    }

    /**
     * Refresh member heartbeat timestamp.
     */
    public function heartbeat(string $groupId, string $memberId): bool
    {
        $group = $this->getGroup($groupId);
        if ($group === null) {
            return false;
        }

        $members = $group->getMembers();
        if (isset($members[$memberId])) {
            $members[$memberId]->heartbeat();
            return true;
        }

        return false;
    }

    /**
     * Commit offset for a topic partition.
     */
    public function commitOffset(string $groupId, TopicPartition $tp, OffsetAndMetadata $offset): void
    {
        $group = $this->getOrCreateGroup($groupId);
        $group->commitOffset($tp, $offset);
    }

    /**
     * Fetch committed offset for a topic partition.
     */
    public function fetchOffset(string $groupId, TopicPartition $tp): ?OffsetAndMetadata
    {
        $group = $this->getGroup($groupId);
        return $group?->getOffset($tp);
    }

    /**
     * Check for timed out members and rebalance affected groups.
     *
     * @param array<string, array<TopicPartition>> $partitionsPerTopic
     * @return array<string> Rebalanced group IDs
     */
    public function checkTimeouts(int $timeoutSeconds, array $partitionsPerTopic): array
    {
        $rebalanced = [];

        foreach ($this->groups as $groupId => $group) {
            $timedOut = [];
            foreach ($group->getMembers() as $memberId => $meta) {
                if ($meta->isExpired($timeoutSeconds)) {
                    $timedOut[] = $memberId;
                }
            }

            if (!empty($timedOut)) {
                foreach ($timedOut as $mId) {
                    $group->removeMember($mId);
                }
                if (!empty($group->getMembers())) {
                    $group->rebalance($partitionsPerTopic);
                    $rebalanced[] = $groupId;
                }
            }
        }

        return $rebalanced;
    }

    /**
     * Get all active consumer groups.
     *
     * @return array<string, ConsumerGroup>
     */
    public function allGroups(): array
    {
        return $this->groups;
    }
}
