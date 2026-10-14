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
 * 2PC resource participant managing local transaction staging and commitment.
 */
class TwoPhaseParticipant
{
    private string $participantId;

    /**
     * @var array<string, TransactionState>
     */
    private array $txStates = [];

    /**
     * @var array<string, mixed> Staged writes awaiting Phase 2 commit
     */
    private array $stagedOperations = [];

    /**
     * @var array<string, mixed> Permanently committed data
     */
    private array $committedData = [];

    private bool $forceAbort = false;

    public function __construct(string $participantId)
    {
        $this->participantId = $participantId;
    }

    public function getParticipantId(): string
    {
        return $this->participantId;
    }

    public function setForceAbort(bool $force): self
    {
        $this->forceAbort = $force;
        return $this;
    }

    /**
     * Phase 1: Prepare local resource for commit or abort.
     */
    public function prepare(string $txId, mixed $operations = null): ParticipantVote
    {
        if ($this->forceAbort) {
            $this->txStates[$txId] = TransactionState::ABORTED;
            return ParticipantVote::VOTE_ABORT;
        }

        $this->stagedOperations[$txId] = $operations;
        $this->txStates[$txId] = TransactionState::PREPARED;

        return ParticipantVote::VOTE_COMMIT;
    }

    /**
     * Phase 2: Commit staged operations.
     */
    public function commit(string $txId): bool
    {
        $state = $this->txStates[$txId] ?? null;

        if ($state === TransactionState::COMMITTED) {
            return true; // Idempotent
        }

        if ($state !== TransactionState::PREPARED) {
            return false;
        }

        if (isset($this->stagedOperations[$txId])) {
            $staged = $this->stagedOperations[$txId];
            if (is_array($staged)) {
                $this->committedData = array_merge($this->committedData, $staged);
            } else {
                $this->committedData[$txId] = $staged;
            }
            unset($this->stagedOperations[$txId]);
        }

        $this->txStates[$txId] = TransactionState::COMMITTED;
        return true;
    }

    /**
     * Phase 2: Rollback and discard staged operations.
     */
    public function rollback(string $txId): bool
    {
        unset($this->stagedOperations[$txId]);
        $this->txStates[$txId] = TransactionState::ABORTED;
        return true;
    }

    public function getTransactionState(string $txId): ?TransactionState
    {
        return $this->txStates[$txId] ?? null;
    }

    public function getCommittedData(): array
    {
        return $this->committedData;
    }
}
