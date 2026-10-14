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

use NetFusion\Core\Kafka\Enums\AutoOffsetReset;
use NetFusion\Core\Kafka\Enums\IsolationLevel;
use NetFusion\Core\Kafka\Exceptions\KafkaException;
use NetFusion\Core\Kafka\KafkaManager;
use NetFusion\Core\Kafka\Models\OffsetAndMetadata;
use NetFusion\Core\Kafka\Models\Record;
use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * High-performance, group-aware Kafka Consumer client.
 */
class KafkaConsumer
{
    public readonly string $memberId;

    /** @var array<string> Subscribed topic names */
    protected array $subscriptions = [];

    /** @var array<string, TopicPartition> Assigned topic partitions indexed by "topic-partition" */
    protected array $assignments = [];

    /** @var array<string, int> Current fetch offset position per "topic-partition" */
    protected array $positions = [];

    protected bool $closed = false;

    public function __construct(
        protected readonly KafkaManager $manager,
        public readonly ?string $groupId = null,
        public readonly string $clientId = 'netfusion-consumer',
        public readonly AutoOffsetReset $autoOffsetReset = AutoOffsetReset::EARLIEST,
        public readonly bool $enableAutoCommit = true,
        public readonly IsolationLevel $isolationLevel = IsolationLevel::READ_UNCOMMITTED,
        ?string $memberId = null
    ) {
        $this->memberId = $memberId ?? ($this->clientId . '-' . bin2hex(random_bytes(4)));
    }

    /**
     * Subscribe to a list of topics.
     *
     * @param array<string> $topics
     */
    public function subscribe(array $topics, ?callable $rebalanceListener = null): void
    {
        $this->subscriptions = array_values(array_unique($topics));

        // Collect all partitions for subscribed topics
        $partitionsPerTopic = [];
        foreach ($this->subscriptions as $topic) {
            $meta = $this->manager->describeTopic($topic);
            if ($meta !== null) {
                $partitionsPerTopic[$topic] = $meta->topicPartitions();
            } else {
                $partitionsPerTopic[$topic] = [];
            }
        }

        if ($this->groupId !== null) {
            $coordinator = $this->manager->getCoordinator();
            $assigned = $coordinator->joinGroup(
                groupId: $this->groupId,
                memberId: $this->memberId,
                clientId: $this->clientId,
                topics: $this->subscriptions,
                partitionsPerTopic: $partitionsPerTopic
            );
            $this->assign($assigned);
        } else {
            // Standalone consumer without group: assign all partitions
            $all = [];
            foreach ($partitionsPerTopic as $parts) {
                foreach ($parts as $tp) {
                    $all[] = $tp;
                }
            }
            $this->assign($all);
        }

        if ($rebalanceListener !== null) {
            $rebalanceListener(array_values($this->assignments));
        }
    }

    /**
     * Manually assign a specific set of TopicPartitions.
     *
     * @param array<TopicPartition> $partitions
     */
    public function assign(array $partitions): void
    {
        $this->assignments = [];

        foreach ($partitions as $tp) {
            $tpKey = $tp->toString();
            $this->assignments[$tpKey] = $tp;

            if (!isset($this->positions[$tpKey])) {
                $this->initPosition($tp);
            }
        }
    }

    /**
     * Fetch messages for assigned partitions.
     *
     * @return array<Record>
     */
    public function poll(int $timeoutMs = 100, int $maxRecords = 500): array
    {
        if ($this->closed) {
            throw new KafkaException('Consumer is closed');
        }

        if (empty($this->assignments)) {
            return [];
        }

        // Send heartbeat if in group
        if ($this->groupId !== null) {
            $this->manager->getCoordinator()->heartbeat($this->groupId, $this->memberId);
        }

        $records = [];
        $collected = 0;

        foreach ($this->assignments as $tpKey => $tp) {
            if ($collected >= $maxRecords) {
                break;
            }

            $currentOffset = $this->positions[$tpKey] ?? 0;
            $log = $this->manager->getLog($tp);

            $fetched = $log->read(
                fromOffset: $currentOffset,
                maxRecords: $maxRecords - $collected,
                isolation: $this->isolationLevel
            );

            foreach ($fetched as $rec) {
                $records[] = $rec;
                $this->positions[$tpKey] = $rec->offset + 1;
                $collected++;
            }
        }

        if ($this->enableAutoCommit && !empty($records) && $this->groupId !== null) {
            $this->commitSync();
        }

        return $records;
    }

    /**
     * Commit current fetch positions or explicit offsets.
     *
     * @param array<string, int|OffsetAndMetadata>|null $offsets
     */
    public function commitSync(?array $offsets = null): void
    {
        if ($this->groupId === null) {
            return;
        }

        $coordinator = $this->manager->getCoordinator();

        if ($offsets === null) {
            foreach ($this->assignments as $tpKey => $tp) {
                if (isset($this->positions[$tpKey])) {
                    $meta = new OffsetAndMetadata($this->positions[$tpKey]);
                    $coordinator->commitOffset($this->groupId, $tp, $meta);
                }
            }
        } else {
            foreach ($offsets as $tpKey => $val) {
                $tp = TopicPartition::fromString($tpKey);
                $meta = ($val instanceof OffsetAndMetadata) ? $val : new OffsetAndMetadata((int) $val);
                $coordinator->commitOffset($this->groupId, $tp, $meta);
            }
        }
    }

    /**
     * Seek to a specific offset for a topic partition.
     */
    public function seek(TopicPartition $tp, int $offset): void
    {
        $tpKey = $tp->toString();
        $this->positions[$tpKey] = $offset;
    }

    /**
     * Seek to beginning (earliest available offset) for partitions.
     *
     * @param array<TopicPartition> $partitions
     */
    public function seekToBeginning(array $partitions = []): void
    {
        $target = !empty($partitions) ? $partitions : array_values($this->assignments);

        foreach ($target as $tp) {
            $log = $this->manager->getLog($tp);
            $this->seek($tp, $log->getLogStartOffset());
        }
    }

    /**
     * Seek to end (latest available offset) for partitions.
     *
     * @param array<TopicPartition> $partitions
     */
    public function seekToEnd(array $partitions = []): void
    {
        $target = !empty($partitions) ? $partitions : array_values($this->assignments);

        foreach ($target as $tp) {
            $log = $this->manager->getLog($tp);
            $this->seek($tp, $log->getLogEndOffset());
        }
    }

    /**
     * Get current fetch position for a topic partition.
     */
    public function position(TopicPartition $tp): int
    {
        $tpKey = $tp->toString();
        if (!isset($this->positions[$tpKey])) {
            $this->initPosition($tp);
        }
        return $this->positions[$tpKey];
    }

    /**
     * Get last committed offset for a topic partition in this group.
     */
    public function committed(TopicPartition $tp): ?OffsetAndMetadata
    {
        if ($this->groupId === null) {
            return null;
        }

        return $this->manager->getCoordinator()->fetchOffset($this->groupId, $tp);
    }

    /**
     * Initialize position based on committed offset or autoOffsetReset strategy.
     */
    protected function initPosition(TopicPartition $tp): void
    {
        $tpKey = $tp->toString();
        $log = $this->manager->getLog($tp);

        if ($this->groupId !== null) {
            $committed = $this->committed($tp);
            if ($committed !== null) {
                $this->positions[$tpKey] = $committed->offset;
                return;
            }
        }

        $this->positions[$tpKey] = match ($this->autoOffsetReset) {
            AutoOffsetReset::EARLIEST => $log->getLogStartOffset(),
            AutoOffsetReset::LATEST   => $log->getLogEndOffset(),
            AutoOffsetReset::NONE     => throw new KafkaException("No committed offset found for {$tpKey}"),
        };
    }

    /**
     * Get currently assigned TopicPartitions.
     *
     * @return array<TopicPartition>
     */
    public function assignment(): array
    {
        return array_values($this->assignments);
    }

    /**
     * Get currently subscribed topics.
     *
     * @return array<string>
     */
    public function subscription(): array
    {
        return $this->subscriptions;
    }

    /**
     * Close consumer and notify group coordinator.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        if ($this->groupId !== null) {
            $partitionsPerTopic = [];
            foreach ($this->subscriptions as $topic) {
                $meta = $this->manager->describeTopic($topic);
                if ($meta !== null) {
                    $partitionsPerTopic[$topic] = $meta->topicPartitions();
                }
            }

            $this->manager->getCoordinator()->leaveGroup($this->groupId, $this->memberId, $partitionsPerTopic);
        }

        $this->closed = true;
    }
}
