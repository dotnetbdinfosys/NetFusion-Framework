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

namespace NetFusion\Core\Kafka\Transaction;

use NetFusion\Core\Kafka\Enums\TransactionState;
use NetFusion\Core\Kafka\Exceptions\KafkaException;
use NetFusion\Core\Kafka\Exceptions\ProducerFencedException;
use NetFusion\Core\Kafka\Models\TopicPartition;

/**
 * Coordinates distributed two-phase commit transactions and manages producer IDs.
 */
class TransactionCoordinator
{
    /** @var array<string, array{producerId: int, epoch: int, state: TransactionState, partitions: array<string, TopicPartition>, offsets: array}> */
    protected array $transactions = [];
    protected int $nextProducerId = 1000;

    /**
     * Allocate or recover producer ID and epoch for a transactional ID.
     *
     * @return array{producerId: int, epoch: int}
     */
    public function initProducerId(?string $transactionalId = null, int $expectedEpoch = -1): array
    {
        if ($transactionalId === null) {
            $pid = $this->nextProducerId++;
            return ['producerId' => $pid, 'epoch' => 0];
        }

        if (isset($this->transactions[$transactionalId])) {
            $txn = &$this->transactions[$transactionalId];
            if ($expectedEpoch >= 0 && $expectedEpoch < $txn['epoch']) {
                throw new ProducerFencedException($txn['producerId'], $txn['epoch'], $expectedEpoch);
            }

            $txn['epoch']++;
            $txn['state'] = TransactionState::READY;
            $txn['partitions'] = [];
            $txn['offsets'] = [];

            return ['producerId' => $txn['producerId'], 'epoch' => $txn['epoch']];
        }

        $pid = $this->nextProducerId++;
        $this->transactions[$transactionalId] = [
            'producerId' => $pid,
            'epoch'      => 0,
            'state'      => TransactionState::READY,
            'partitions' => [],
            'offsets'    => [],
        ];

        return ['producerId' => $pid, 'epoch' => 0];
    }

    /**
     * Begin an ongoing transaction.
     */
    public function beginTransaction(string $transactionalId): void
    {
        if (!isset($this->transactions[$transactionalId])) {
            $this->initProducerId($transactionalId);
        }

        $txn = &$this->transactions[$transactionalId];
        if ($txn['state'] === TransactionState::ONGOING) {
            throw new KafkaException("Transaction for {$transactionalId} is already ongoing");
        }

        $txn['state'] = TransactionState::ONGOING;
        $txn['partitions'] = [];
        $txn['offsets'] = [];
    }

    /**
     * Register partitions involved in this transaction.
     *
     * @param array<TopicPartition> $partitions
     */
    public function addPartitionsToTxn(string $transactionalId, array $partitions): void
    {
        if (!isset($this->transactions[$transactionalId])) {
            throw new KafkaException("Transactional ID {$transactionalId} not initialized");
        }

        $txn = &$this->transactions[$transactionalId];
        if ($txn['state'] !== TransactionState::ONGOING) {
            throw new KafkaException("Cannot add partitions to transaction in state {$txn['state']->value}");
        }

        foreach ($partitions as $tp) {
            $txn['partitions'][$tp->toString()] = $tp;
        }
    }

    /**
     * Stash consumer group offsets committed within this transaction.
     */
    public function sendOffsetsToTxn(string $transactionalId, array $offsets, string $groupId): void
    {
        if (!isset($this->transactions[$transactionalId])) {
            throw new KafkaException("Transactional ID {$transactionalId} not initialized");
        }

        $txn = &$this->transactions[$transactionalId];
        if ($txn['state'] !== TransactionState::ONGOING) {
            throw new KafkaException("Cannot send offsets to transaction in state {$txn['state']->value}");
        }

        $txn['offsets'][$groupId] = $offsets;
    }

    /**
     * Commit or abort an ongoing transaction, dispatching control markers.
     *
     * @param callable $markerCallback fn(TopicPartition $tp, int $pid, int $epoch, string $type)
     */
    public function endTransaction(string $transactionalId, bool $commit, callable $markerCallback): void
    {
        if (!isset($this->transactions[$transactionalId])) {
            throw new KafkaException("Transactional ID {$transactionalId} not initialized");
        }

        $txn = &$this->transactions[$transactionalId];
        if ($txn['state'] !== TransactionState::ONGOING) {
            throw new KafkaException("Cannot complete transaction in state {$txn['state']->value}");
        }

        $markerType = $commit ? 'COMMIT' : 'ABORT';
        $txn['state'] = $commit ? TransactionState::PREPARE_COMMIT : TransactionState::PREPARE_ABORT;

        // Write marker to each involved topic partition
        foreach ($txn['partitions'] as $tp) {
            $markerCallback($tp, $txn['producerId'], $txn['epoch'], $markerType);
        }

        $txn['state'] = $commit ? TransactionState::COMMITTED : TransactionState::ABORTED;
        // Reset back to ready for next transaction
        $txn['state'] = TransactionState::READY;
        $txn['partitions'] = [];
    }

    /**
     * Retrieve transaction metadata.
     */
    public function getTransaction(string $transactionalId): ?array
    {
        return $this->transactions[$transactionalId] ?? null;
    }
}
