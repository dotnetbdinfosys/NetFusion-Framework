<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\DistributedDb\TwoPhaseCommit;

/**
 * Two-Phase Commit (2PC) coordinator guaranteeing distributed atomicity across shards.
 */
class TwoPhaseCoordinator
{
    /**
     * @var array<string, TwoPhaseParticipant>
     */
    private array $participants = [];

    /**
     * @var array<string, array{state: TransactionState, participants: array<string>, votes: array<string, ParticipantVote>, timestamp: int}>
     */
    private array $transactions = [];

    public function __construct(array $participants = [])
    {
        foreach ($participants as $participant) {
            $this->addParticipant($participant);
        }
    }

    public function addParticipant(TwoPhaseParticipant $participant): self
    {
        $this->participants[$participant->getParticipantId()] = $participant;
        return $this;
    }

    public function getParticipant(string $id): ?TwoPhaseParticipant
    {
        return $this->participants[$id] ?? null;
    }

    /**
     * @return array<string, TwoPhaseParticipant>
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    /**
     * Begin a new 2PC transaction.
     */
    public function beginTransaction(?string $txId = null): string
    {
        $txId ??= uniqid('tx_2pc_', true);
        $this->transactions[$txId] = [
            'state' => TransactionState::INITIAL,
            'participants' => [],
            'votes' => [],
            'timestamp' => time(),
        ];

        return $txId;
    }

    public function getTransactionState(string $txId): ?TransactionState
    {
        return $this->transactions[$txId]['state'] ?? null;
    }

    /**
     * Execute atomic distributed transaction across participating shards.
     *
     * @param array<string, mixed> $operationsByParticipant [participantId => stagedOperations]
     * @param string|null $txId
     * @return array{
     *     tx_id: string,
     *     state: TransactionState,
     *     success: bool,
     *     votes: array<string, ParticipantVote>
     * }
     */
    public function executeAtomic(array $operationsByParticipant, ?string $txId = null): array
    {
        $txId = $this->beginTransaction($txId);
        $participatingIds = array_keys($operationsByParticipant);
        $this->transactions[$txId]['participants'] = $participatingIds;

        // Phase 1: Prepare
        $this->transactions[$txId]['state'] = TransactionState::PREPARING;
        $allCommit = true;
        $votes = [];

        foreach ($operationsByParticipant as $participantId => $operations) {
            $participant = $this->getParticipant($participantId);
            if ($participant === null) {
                $allCommit = false;
                $votes[$participantId] = ParticipantVote::VOTE_ABORT;
                break;
            }

            $vote = $participant->prepare($txId, $operations);
            $votes[$participantId] = $vote;

            if ($vote !== ParticipantVote::VOTE_COMMIT) {
                $allCommit = false;
                break;
            }
        }

        $this->transactions[$txId]['votes'] = $votes;

        // Phase 2: Commit or Abort
        if ($allCommit) {
            $this->transactions[$txId]['state'] = TransactionState::COMMITTING;
            foreach ($participatingIds as $pId) {
                $p = $this->getParticipant($pId);
                $p?->commit($txId);
            }
            $this->transactions[$txId]['state'] = TransactionState::COMMITTED;

            return [
                'tx_id' => $txId,
                'state' => TransactionState::COMMITTED,
                'success' => true,
                'votes' => $votes,
            ];
        }

        // Abort
        $this->transactions[$txId]['state'] = TransactionState::ABORTING;
        foreach ($participatingIds as $pId) {
            $p = $this->getParticipant($pId);
            $p?->rollback($txId);
        }
        $this->transactions[$txId]['state'] = TransactionState::ABORTED;

        return [
            'tx_id' => $txId,
            'state' => TransactionState::ABORTED,
            'success' => false,
            'votes' => $votes,
        ];
    }
}
