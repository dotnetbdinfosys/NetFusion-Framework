<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Master orchestrator facade managing accounts, SMT state, MMR history, and batches.
 *
 * @package    NetFusion\Core\Rollup
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup;

use NetFusion\Core\Rollup\Enums\RollupBatchStatus;
use NetFusion\Core\Rollup\Enums\StateTransitionType;
use NetFusion\Core\Rollup\Exceptions\RollupException;
use NetFusion\Core\Rollup\Execution\RollupBatch;
use NetFusion\Core\Rollup\Execution\RollupExecutionEngine;
use NetFusion\Core\Rollup\Execution\StateTransition;
use NetFusion\Core\Rollup\State\AccountLeaf;
use NetFusion\Core\Rollup\State\MerkleMountainRange;
use NetFusion\Core\Rollup\State\SparseMerkleTree;

class RollupManager
{
    protected SparseMerkleTree $stateTree;
    protected MerkleMountainRange $mmr;
    protected RollupExecutionEngine $engine;

    /**
     * Active accounts indexed by account ID.
     *
     * @var array<string, AccountLeaf>
     */
    protected array $accounts = [];

    /**
     * Mempool of pending state transitions.
     *
     * @var array<int, StateTransition>
     */
    protected array $pendingTransitions = [];

    /**
     * Historical committed batches indexed by batch ID.
     *
     * @var array<int, RollupBatch>
     */
    protected array $batches = [];

    protected int $nextBatchId = 1;

    public function __construct(
        int $treeDepth = 16,
        ?SparseMerkleTree $stateTree = null,
        ?MerkleMountainRange $mmr = null,
        ?RollupExecutionEngine $engine = null
    ) {
        $this->stateTree = $stateTree ?? new SparseMerkleTree($treeDepth);
        $this->mmr       = $mmr ?? new MerkleMountainRange();
        $this->engine    = $engine ?? new RollupExecutionEngine();
    }

    /**
     * Register or initialize an account in the state tree.
     */
    public function registerAccount(
        string $accountId,
        ?string $publicKey = null,
        float $initialBalance = 0.0,
        array $metadata = []
    ): AccountLeaf {
        $pk = $publicKey ?? hash('sha256', 'PK_' . $accountId);
        $account = new AccountLeaf($accountId, $pk, $initialBalance, 0, $metadata);
        
        $this->accounts[$accountId] = $account;
        $this->stateTree->update($accountId, $account->getHash());

        return $account;
    }

    public function getAccount(string $accountId): ?AccountLeaf
    {
        return $this->accounts[$accountId] ?? null;
    }

    /**
     * @return array<string, AccountLeaf>
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    /**
     * Submit an atomic state transition to the pending mempool.
     */
    public function submitTransition(StateTransition $transition): string
    {
        $this->pendingTransitions[] = $transition;
        return $transition->getTransitionId();
    }

    /**
     * Create and submit a transfer transaction.
     */
    public function transfer(
        string $from,
        string $to,
        float $amount,
        ?string $secretKey = null
    ): StateTransition {
        $sender = $this->getAccount($from);
        if ($sender === null) {
            throw new RollupException("Sender account does not exist: {$from}");
        }

        $transition = new StateTransition(
            bin2hex(random_bytes(16)),
            StateTransitionType::TRANSFER,
            $from,
            $to,
            $amount,
            $sender->getNonce()
        );

        if ($secretKey !== null) {
            $transition = $transition->signWithSecret($secretKey);
        } else {
            $transition = $transition->withSignature(hash('sha256', 'SIMULATED_SIG_' . $from));
        }

        $this->submitTransition($transition);
        return $transition;
    }

    /**
     * Mint balance to an account.
     */
    public function mint(string $to, float $amount): StateTransition
    {
        $transition = new StateTransition(
            bin2hex(random_bytes(16)),
            StateTransitionType::MINT,
            '0x0000000000000000',
            $to,
            $amount,
            0
        );
        $transition = $transition->withSignature(hash('sha256', 'GENESIS_MINT'));
        $this->submitTransition($transition);
        return $transition;
    }

    /**
     * Burn balance from an account.
     */
    public function burn(string $from, float $amount): StateTransition
    {
        $sender = $this->getAccount($from);
        if ($sender === null) {
            throw new RollupException("Account to burn does not exist: {$from}");
        }

        $transition = new StateTransition(
            bin2hex(random_bytes(16)),
            StateTransitionType::BURN,
            $from,
            '0x0000000000000000',
            $amount,
            $sender->getNonce()
        );
        $transition = $transition->withSignature(hash('sha256', 'BURN_SIG_' . $from));
        $this->submitTransition($transition);
        return $transition;
    }

    /**
     * Process pending transitions into a proved & committed Rollup batch.
     */
    public function processBatch(int $maxTransitions = 50): ?RollupBatch
    {
        if (empty($this->pendingTransitions)) {
            return null;
        }

        $chunk = array_splice($this->pendingTransitions, 0, $maxTransitions);
        $batchId = $this->nextBatchId++;

        $batch = $this->engine->executeBatch(
            $this->accounts,
            $this->stateTree,
            $chunk,
            $batchId
        );

        // Commit batch and append to history MMR
        $batch = $batch->withStatus(RollupBatchStatus::COMMITTED);
        $this->mmr->append($batch->getBatchHash());

        $this->batches[$batchId] = $batch;
        return $batch;
    }

    /**
     * Get current Sparse Merkle Tree state root.
     */
    public function getStateRoot(): string
    {
        return $this->stateTree->getRoot();
    }

    /**
     * Get current Merkle-Mountain Range (MMR) historical root.
     */
    public function getMmrRoot(): string
    {
        return $this->mmr->getRoot();
    }

    /**
     * Generate state inclusion proof for account.
     */
    public function getAccountProof(string $accountId): array
    {
        return $this->stateTree->getProof($accountId);
    }

    /**
     * Verify whether an account state is included in current state root.
     */
    public function verifyAccountInclusion(string $accountId): bool
    {
        $account = $this->getAccount($accountId);
        if ($account === null) {
            return false;
        }

        $proof = $this->getAccountProof($accountId);
        return $this->stateTree->verifyProof($accountId, $account->getHash(), $proof, $this->getStateRoot());
    }

    /**
     * Generate MMR inclusion proof for a committed batch by its 0-based index.
     */
    public function getBatchMmrProof(int $batchIndex): array
    {
        return $this->mmr->getProof($batchIndex);
    }

    /**
     * Verify historical batch inclusion against MMR root.
     */
    public function verifyBatchInMmr(int $batchIndex, string $batchHash): bool
    {
        $proof = $this->getBatchMmrProof($batchIndex);
        return MerkleMountainRange::verifyProof($batchHash, $proof, $this->getMmrRoot());
    }

    public function getBatches(): array
    {
        return $this->batches;
    }

    public function getBatch(int $batchId): ?RollupBatch
    {
        return $this->batches[$batchId] ?? null;
    }

    public function getPendingTransitions(): array
    {
        return $this->pendingTransitions;
    }

    public function getStateTree(): SparseMerkleTree
    {
        return $this->stateTree;
    }

    public function getMmr(): MerkleMountainRange
    {
        return $this->mmr;
    }

    public function getExecutionEngine(): RollupExecutionEngine
    {
        return $this->engine;
    }
}
