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
use NetFusion\Core\Kafka\Consumer\Assignors\RangeAssignor;
use NetFusion\Core\Kafka\Enums\RebalanceState;
use NetFusion\Core\Kafka\Models\ConsumerMetadata;
use NetFusion\Core\Kafka\Models\OffsetAndMetadata;
use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Manages membership, generation ID, rebalances, and committed offsets for a consumer group.
 */
class ConsumerGroup
{
    protected int $generationId = 0;
    protected ?string $leaderId = null;
    protected RebalanceState $state = RebalanceState::STABLE;

    /** @var array<string, ConsumerMetadata> Member ID => ConsumerMetadata */
    protected array $members = [];

    /** @var array<string, array<string>> Member ID => array of subscribed topic names */
    protected array $subscriptions = [];

    /** @var array<string, OffsetAndMetadata> "topic-partition" => OffsetAndMetadata */
    protected array $committedOffsets = [];

    protected PartitionAssignorInterface $assignor;

    public function __construct(
        public readonly string $groupId,
        ?PartitionAssignorInterface $assignor = null
    ) {
        $this->assignor = $assignor ?? new RangeAssignor();
    }

    /**
     * Add or update a member in this group.
     *
     * @param array<string> $topics
     */
    public function addMember(string $memberId, string $clientId, array $topics): ConsumerMetadata
    {
        if (empty($this->members)) {
            $this->leaderId = $memberId;
        }

        $meta = new ConsumerMetadata($memberId, $clientId);
        $this->members[$memberId] = $meta;
        $this->subscriptions[$memberId] = $topics;
        $this->state = RebalanceState::PREPARING_REBALANCE;

        return $meta;
    }

    /**
     * Remove a member from the group.
     */
    public function removeMember(string $memberId): void
    {
        unset($this->members[$memberId]);
        unset($this->subscriptions[$memberId]);

        if ($this->leaderId === $memberId) {
            $this->leaderId = !empty($this->members) ? array_key_first($this->members) : null;
        }

        if (empty($this->members)) {
            $this->state = RebalanceState::DEAD;
        } else {
            $this->state = RebalanceState::PREPARING_REBALANCE;
        }
    }

    /**
     * Trigger partition rebalance across all group members.
     *
     * @param array<string, array<TopicPartition>> $partitionsPerTopic
     * @return array<string, array<TopicPartition>> Member ID => assigned TopicPartitions
     */
    public function rebalance(array $partitionsPerTopic): array
    {
        $this->state = RebalanceState::COMPLETING_REBALANCE;
        $this->generationId++;

        $assignments = $this->assignor->assign($partitionsPerTopic, $this->subscriptions);

        foreach ($assignments as $memberId => $partitions) {
            if (isset($this->members[$memberId])) {
                $this->members[$memberId]->assignPartitions($partitions);
            }
        }

        $this->state = RebalanceState::STABLE;

        return $assignments;
    }

    /**
     * Commit an offset for a TopicPartition.
     */
    public function commitOffset(TopicPartition $tp, OffsetAndMetadata $offset): void
    {
        $this->committedOffsets[$tp->toString()] = $offset;
    }

    /**
     * Retrieve committed offset for a TopicPartition.
     */
    public function getOffset(TopicPartition $tp): ?OffsetAndMetadata
    {
        return $this->committedOffsets[$tp->toString()] ?? null;
    }

    public function getGenerationId(): int
    {
        return $this->generationId;
    }

    public function getLeaderId(): ?string
    {
        return $this->leaderId;
    }

    public function getState(): RebalanceState
    {
        return $this->state;
    }

    /**
     * @return array<string, ConsumerMetadata>
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    /**
     * @return array<string, OffsetAndMetadata>
     */
    public function getCommittedOffsets(): array
    {
        return $this->committedOffsets;
    }

    public function getAssignor(): PartitionAssignorInterface
    {
        return $this->assignor;
    }

    public function setAssignor(PartitionAssignorInterface $assignor): void
    {
        $this->assignor = $assignor;
    }
}
