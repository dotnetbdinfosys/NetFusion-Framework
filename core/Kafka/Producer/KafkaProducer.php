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

namespace NetFusion\Core\Kafka\Producer;

use NetFusion\Core\Kafka\Enums\Acks;
use NetFusion\Core\Kafka\Exceptions\KafkaException;
use NetFusion\Core\Kafka\KafkaManager;
use NetFusion\Core\Kafka\Models\Record;
use NetFusion\Core\Kafka\Models\TopicPartition;
use NetFusion\Core\Kafka\Producer\Partitioners\Murmur2Partitioner;
use NetFusion\Core\Kafka\Producer\Partitioners\PartitionerInterface;
use NetFusion\Core\Kafka\Transaction\TransactionCoordinator;

/**
 * High-throughput, idempotent and transactional Kafka Producer client.
 */
class KafkaProducer
{
    protected PartitionerInterface $partitioner;
    protected RecordAccumulator $accumulator;
    protected IdempotentProducer $idempotent;
    protected ?TransactionCoordinator $txCoordinator;
    protected bool $inTransaction = false;

    public function __construct(
        protected readonly KafkaManager $manager,
        protected readonly ?string $transactionalId = null,
        protected readonly Acks $acks = Acks::ALL,
        ?PartitionerInterface $partitioner = null,
        ?RecordAccumulator $accumulator = null,
        ?IdempotentProducer $idempotent = null,
        ?TransactionCoordinator $txCoordinator = null
    ) {
        $this->partitioner = $partitioner ?? new Murmur2Partitioner();
        $this->accumulator = $accumulator ?? new RecordAccumulator();
        $this->txCoordinator = $txCoordinator ?? $manager->getTransactionCoordinator();

        if ($this->transactionalId !== null && $this->txCoordinator !== null) {
            $alloc = $this->txCoordinator->initProducerId($this->transactionalId);
            $this->idempotent = new IdempotentProducer($alloc['producerId'], $alloc['epoch']);
        } else {
            $this->idempotent = $idempotent ?? new IdempotentProducer();
        }
    }

    /**
     * Send a record asynchronously or directly to target partition log.
     */
    public function send(
        string $topic,
        string $value,
        ?string $key = null,
        ?int $partition = null,
        array $headers = [],
        ?int $timestamp = null
    ): Record {
        $topicMeta = $this->manager->describeTopic($topic);
        if ($topicMeta === null) {
            // Auto-create topic with 1 partition if topic does not exist
            $topicMeta = $this->manager->createTopic($topic, 1);
        }

        if ($partition === null) {
            $partition = $this->partitioner->partition($topic, $key, $value, $topicMeta->partitionsCount);
        }

        if ($partition < 0 || $partition >= $topicMeta->partitionsCount) {
            throw new KafkaException(sprintf('Partition %d is invalid for topic "%s"', $partition, $topic));
        }

        $tp = new TopicPartition($topic, $partition);
        $seq = $this->idempotent->nextSequence($tp);
        $pid = $this->idempotent->getProducerId();
        $epoch = $this->idempotent->getProducerEpoch();

        // If in transaction, register partition with coordinator
        if ($this->inTransaction && $this->transactionalId !== null && $this->txCoordinator !== null) {
            $this->txCoordinator->addPartitionsToTxn($this->transactionalId, [$tp]);
        }

        $log = $this->manager->getLog($tp);
        $record = $log->append(
            value: $value,
            key: $key,
            headers: $headers,
            producerId: $pid,
            producerEpoch: $epoch,
            sequenceNumber: $seq,
            isControl: false,
            controlType: null,
            timestamp: $timestamp
        );

        $this->accumulator->append($tp, $record->toArray());

        return $record;
    }

    /**
     * Flush all buffered batches in the accumulator.
     */
    public function flush(): int
    {
        $drained = $this->accumulator->drain();
        $count = 0;
        foreach ($drained as $batch) {
            $count += count($batch);
        }
        return $count;
    }

    /**
     * Initialize transactional state for this producer.
     */
    public function initTransactions(): void
    {
        if ($this->transactionalId === null) {
            throw new KafkaException('Cannot initTransactions without a configured transactional.id');
        }

        if ($this->txCoordinator === null) {
            throw new KafkaException('TransactionCoordinator is not available');
        }

        $alloc = $this->txCoordinator->initProducerId($this->transactionalId);
        $this->idempotent = new IdempotentProducer($alloc['producerId'], $alloc['epoch']);
        $this->inTransaction = false;
    }

    /**
     * Begin a new transaction.
     */
    public function beginTransaction(): void
    {
        if ($this->transactionalId === null || $this->txCoordinator === null) {
            throw new KafkaException('Cannot beginTransaction without transactional.id and coordinator');
        }

        if ($this->inTransaction) {
            throw new KafkaException('A transaction is already in progress');
        }

        $this->txCoordinator->beginTransaction($this->transactionalId);
        $this->inTransaction = true;
    }

    /**
     * Send consumer group committed offsets as part of this atomic transaction.
     */
    public function sendOffsetsToTransaction(array $offsets, string $consumerGroupId): void
    {
        if (!$this->inTransaction || $this->transactionalId === null || $this->txCoordinator === null) {
            throw new KafkaException('Cannot sendOffsetsToTransaction outside of an active transaction');
        }

        $this->txCoordinator->sendOffsetsToTxn($this->transactionalId, $offsets, $consumerGroupId);
    }

    /**
     * Commit the active transaction, writing COMMIT markers to all involved partitions.
     */
    public function commitTransaction(): void
    {
        if (!$this->inTransaction || $this->transactionalId === null || $this->txCoordinator === null) {
            throw new KafkaException('Cannot commitTransaction outside of an active transaction');
        }

        $this->txCoordinator->endTransaction(
            $this->transactionalId,
            commit: true,
            markerCallback: function (TopicPartition $tp, int $pid, int $epoch, string $type) {
                $log = $this->manager->getLog($tp);
                $log->append(
                    value: '',
                    key: null,
                    headers: [],
                    producerId: $pid,
                    producerEpoch: $epoch,
                    sequenceNumber: 0,
                    isControl: true,
                    controlType: $type
                );
            }
        );

        $this->inTransaction = false;
    }

    /**
     * Abort the active transaction, writing ABORT markers to all involved partitions.
     */
    public function abortTransaction(): void
    {
        if (!$this->inTransaction || $this->transactionalId === null || $this->txCoordinator === null) {
            throw new KafkaException('Cannot abortTransaction outside of an active transaction');
        }

        $this->txCoordinator->endTransaction(
            $this->transactionalId,
            commit: false,
            markerCallback: function (TopicPartition $tp, int $pid, int $epoch, string $type) {
                $log = $this->manager->getLog($tp);
                $log->append(
                    value: '',
                    key: null,
                    headers: [],
                    producerId: $pid,
                    producerEpoch: $epoch,
                    sequenceNumber: 0,
                    isControl: true,
                    controlType: $type
                );
            }
        );

        $this->inTransaction = false;
    }

    public function getProducerId(): int
    {
        return $this->idempotent->getProducerId();
    }

    public function getProducerEpoch(): int
    {
        return $this->idempotent->getProducerEpoch();
    }

    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }
}
