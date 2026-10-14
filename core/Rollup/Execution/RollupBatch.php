<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Rollup Batch aggregating transitions and holding state transition proofs.
 *
 * @package    NetFusion\Core\Rollup\Execution
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\Execution;

use NetFusion\Core\Rollup\Enums\RollupBatchStatus;

class RollupBatch
{
    /**
     * @param int $batchId
     * @param string $previousStateRoot
     * @param string $newStateRoot
     * @param array<int, StateTransition> $transitions
     * @param string $batchHash
     * @param RollupBatchStatus $status
     * @param array<string, mixed> $validityProof
     * @param int $createdAt
     */
    public function __construct(
        protected readonly int $batchId,
        protected readonly string $previousStateRoot,
        protected readonly string $newStateRoot,
        protected readonly array $transitions,
        protected ?string $batchHash = null,
        protected RollupBatchStatus $status = RollupBatchStatus::PENDING,
        protected array $validityProof = [],
        protected readonly int $createdAt = 0
    ) {
        if ($this->batchHash === null || $this->batchHash === '') {
            $this->batchHash = $this->computeBatchHash();
        }
    }

    public function getBatchId(): int
    {
        return $this->batchId;
    }

    public function getPreviousStateRoot(): string
    {
        return $this->previousStateRoot;
    }

    public function getNewStateRoot(): string
    {
        return $this->newStateRoot;
    }

    /**
     * @return array<int, StateTransition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getBatchHash(): string
    {
        return $this->batchHash ?? $this->computeBatchHash();
    }

    public function getStatus(): RollupBatchStatus
    {
        return $this->status;
    }

    public function getValidityProof(): array
    {
        return $this->validityProof;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt > 0 ? $this->createdAt : time();
    }

    public function getTransitionsCount(): int
    {
        return count($this->transitions);
    }

    /**
     * Deterministic batch hash calculation.
     */
    public function computeBatchHash(): string
    {
        $transitionHashes = array_map(
            fn(StateTransition $t) => $t->getPayloadHash(),
            $this->transitions
        );

        $payload = sprintf(
            '%d:%s:%s:%s',
            $this->batchId,
            $this->previousStateRoot,
            $this->newStateRoot,
            implode(',', $transitionHashes)
        );

        return hash('sha256', $payload);
    }

    public function withStatus(RollupBatchStatus $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withValidityProof(array $proof): self
    {
        $clone = clone $this;
        $clone->validityProof = $proof;
        return $clone;
    }

    public function toArray(): array
    {
        return [
            'batch_id'            => $this->batchId,
            'previous_state_root' => $this->previousStateRoot,
            'new_state_root'      => $this->newStateRoot,
            'transitions_count'   => count($this->transitions),
            'transitions'         => array_map(fn(StateTransition $t) => $t->toArray(), $this->transitions),
            'batch_hash'          => $this->getBatchHash(),
            'status'              => $this->status->value,
            'validity_proof'      => $this->validityProof,
            'created_at'          => $this->getCreatedAt(),
        ];
    }
}
