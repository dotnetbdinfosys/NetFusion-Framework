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

namespace NetFusion\Core\Kafka;

use NetFusion\Core\Kafka\Consumer\GroupCoordinator;
use NetFusion\Core\Kafka\Consumer\KafkaConsumer;
use NetFusion\Core\Kafka\Enums\Acks;
use NetFusion\Core\Kafka\Enums\AutoOffsetReset;
use NetFusion\Core\Kafka\Enums\IsolationLevel;
use NetFusion\Core\Kafka\Exceptions\KafkaException;
use NetFusion\Core\Kafka\Models\Record;
use NetFusion\Core\Kafka\Models\TopicMetadata;
use NetFusion\Core\Kafka\Models\TopicPartition;
use NetFusion\Core\Kafka\Producer\KafkaProducer;
use NetFusion\Core\Kafka\Storage\PartitionLog;
use NetFusion\Core\Kafka\Storage\RetentionManager;
use NetFusion\Core\Kafka\Transaction\TransactionCoordinator;

/**
 * Master Enterprise Distributed Event Streaming Platform (Kafka Architecture) Facade.
 */
class KafkaManager
{
    /** @var array<string, TopicMetadata> Topic name => TopicMetadata */
    protected array $topics = [];

    /** @var array<string, PartitionLog> "topic-partition" => PartitionLog */
    protected array $partitionLogs = [];

    protected GroupCoordinator $coordinator;
    protected TransactionCoordinator $txCoordinator;
    protected RetentionManager $retentionManager;

    public function __construct(
        ?GroupCoordinator $coordinator = null,
        ?TransactionCoordinator $txCoordinator = null,
        ?RetentionManager $retentionManager = null
    ) {
        $this->coordinator = $coordinator ?? new GroupCoordinator();
        $this->txCoordinator = $txCoordinator ?? new TransactionCoordinator();
        $this->retentionManager = $retentionManager ?? new RetentionManager();
    }

    /**
     * Create a new topic with the given partition count and configuration.
     */
    public function createTopic(
        string $name,
        int $partitions = 1,
        int $replicationFactor = 1,
        array $config = []
    ): TopicMetadata {
        if ($partitions <= 0) {
            throw new KafkaException('Partitions count must be at least 1');
        }

        if (isset($this->topics[$name])) {
            throw new KafkaException("Topic '{$name}' already exists");
        }

        $meta = new TopicMetadata($name, $partitions, $replicationFactor, $config);
        $this->topics[$name] = $meta;

        // Initialize partition logs
        for ($p = 0; $p < $partitions; $p++) {
            $tp = new TopicPartition($name, $p);
            $this->partitionLogs[$tp->toString()] = new PartitionLog($tp);
        }

        return $meta;
    }

    /**
     * Delete an existing topic and purge its logs.
     */
    public function deleteTopic(string $name): bool
    {
        if (!isset($this->topics[$name])) {
            return false;
        }

        $meta = $this->topics[$name];
        foreach ($meta->partitions() as $p) {
            $tpKey = "{$name}-{$p}";
            unset($this->partitionLogs[$tpKey]);
        }

        unset($this->topics[$name]);
        return true;
    }

    /**
     * Get metadata for a specific topic.
     */
    public function describeTopic(string $name): ?TopicMetadata
    {
        return $this->topics[$name] ?? null;
    }

    /**
     * Get all registered topics.
     *
     * @return array<string, TopicMetadata>
     */
    public function listTopics(): array
    {
        return $this->topics;
    }

    /**
     * Check if a topic exists.
     */
    public function hasTopic(string $name): bool
    {
        return isset($this->topics[$name]);
    }

    /**
     * Retrieve the append-only commit log for a given TopicPartition.
     */
    public function getLog(TopicPartition $tp): PartitionLog
    {
        $key = $tp->toString();
        if (!isset($this->partitionLogs[$key])) {
            // Auto-create topic/partition if topic is unknown
            if (!isset($this->topics[$tp->topic])) {
                $this->createTopic($tp->topic, max(1, $tp->partition + 1));
            } elseif (!isset($this->partitionLogs[$key])) {
                $this->partitionLogs[$key] = new PartitionLog($tp);
            }
        }

        return $this->partitionLogs[$key];
    }

    /**
     * Create a new KafkaProducer instance.
     */
    public function producer(?string $transactionalId = null, Acks $acks = Acks::ALL): KafkaProducer
    {
        return new KafkaProducer(
            manager: $this,
            transactionalId: $transactionalId,
            acks: $acks,
            txCoordinator: $this->txCoordinator
        );
    }

    /**
     * Create a new KafkaConsumer instance.
     */
    public function consumer(
        ?string $groupId = null,
        AutoOffsetReset $autoOffsetReset = AutoOffsetReset::EARLIEST,
        bool $enableAutoCommit = true,
        IsolationLevel $isolation = IsolationLevel::READ_UNCOMMITTED,
        ?string $memberId = null
    ): KafkaConsumer {
        return new KafkaConsumer(
            manager: $this,
            groupId: $groupId,
            autoOffsetReset: $autoOffsetReset,
            enableAutoCommit: $enableAutoCommit,
            isolationLevel: $isolation,
            memberId: $memberId
        );
    }

    /**
     * Convenience helper to publish a record directly.
     */
    public function produce(
        string $topic,
        string $value,
        ?string $key = null,
        ?int $partition = null,
        array $headers = []
    ): Record {
        return $this->producer()->send($topic, $value, $key, $partition, $headers);
    }

    /**
     * Enforce retention policies across all partition logs.
     *
     * @return int Number of records pruned or compacted
     */
    public function enforceRetention(): int
    {
        $totalPruned = 0;

        foreach ($this->partitionLogs as $tpKey => $log) {
            $topic = $log->topicPartition->topic;
            $meta = $this->describeTopic($topic);
            $policy = $meta?->cleanupPolicy() ?? \NetFusion\Core\Kafka\Enums\CleanupPolicy::DELETE;
            $retentionSec = isset($meta->config['retention.ms']) ? (int) ($meta->config['retention.ms'] / 1000) : 0;
            $retentionBytes = (int) ($meta->config['retention.bytes'] ?? 0);

            $totalPruned += $this->retentionManager->enforce(
                log: $log,
                retentionSeconds: $retentionSec,
                retentionBytes: $retentionBytes,
                policy: $policy
            );
        }

        return $totalPruned;
    }

    public function getCoordinator(): GroupCoordinator
    {
        return $this->coordinator;
    }

    public function getTransactionCoordinator(): TransactionCoordinator
    {
        return $this->txCoordinator;
    }

    public function getRetentionManager(): RetentionManager
    {
        return $this->retentionManager;
    }

    /**
     * Reset all internal broker state.
     */
    public function clear(): void
    {
        $this->topics = [];
        $this->partitionLogs = [];
        $this->coordinator = new GroupCoordinator();
        $this->txCoordinator = new TransactionCoordinator();
        $this->retentionManager = new RetentionManager();
    }
}
