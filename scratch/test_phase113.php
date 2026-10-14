<?php
/**
 * NetFusion Framework - Phase 113 Test Suite
 *
 * Enterprise Zero-Knowledge Rollup (ZK-Rollup), Merkle-Mountain Range (MMR) & State Transition Execution Fabric.
 *
 * @package    NetFusion\Tests\Phase113
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../core/Support/helpers.php';

// Autoload framework classes manually if no composer autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'NetFusion\\Core\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../core/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});



use NetFusion\Core\Rollup\Enums\RollupBatchStatus;
use NetFusion\Core\Rollup\Enums\StateTransitionType;
use NetFusion\Core\Rollup\Exceptions\InvalidStateTransitionException;
use NetFusion\Core\Rollup\Exceptions\MmrInclusionProofException;
use NetFusion\Core\Rollup\Execution\RollupBatch;
use NetFusion\Core\Rollup\Execution\RollupExecutionEngine;
use NetFusion\Core\Rollup\Execution\StateTransition;
use NetFusion\Core\Rollup\RollupManager;
use NetFusion\Core\Rollup\State\AccountLeaf;
use NetFusion\Core\Rollup\State\MerkleMountainRange;
use NetFusion\Core\Rollup\State\SparseMerkleTree;

$testsPassed = 0;
$totalTests = 10;

function assertTrue(bool $condition, string $msg): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
}

function assertEquals(mixed $expected, mixed $actual, string $msg): void {
    if ($expected !== $actual) {
        throw new RuntimeException("Assertion failed: {$msg}. Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

echo "=== NetFusion Phase 113: ZK-Rollup, MMR & State Transition Test Suite ===" . PHP_EOL . PHP_EOL;

// Test 1: SMT Basic Operations and Root Determinism
try {
    echo "Running Test 1: Sparse Merkle Tree (SMT) Basic Operations and Determinism... ";
    $smt = new SparseMerkleTree(16);
    $initialRoot = $smt->getRoot();
    assertTrue(strlen($initialRoot) === 64, "Initial root must be 64-char hex SHA256");

    $leafHashA = hash('sha256', 'ACCOUNT_A_STATE');
    $root1 = $smt->update('account_a', $leafHashA);
    assertTrue($root1 !== $initialRoot, "Root must change when leaf is updated");

    $root1Again = $smt->update('account_a', $leafHashA);
    assertEquals($root1, $root1Again, "Identical update must yield identical root");

    $leafHashB = hash('sha256', 'ACCOUNT_B_STATE');
    $root2 = $smt->update('account_b', $leafHashB);
    assertTrue($root2 !== $root1, "Second leaf update must yield new root");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 2: SMT Inclusion Proof and Verification
try {
    echo "Running Test 2: SMT Inclusion Audit Proofs & Verification... ";
    $smt = new SparseMerkleTree(16);
    $leafHash = hash('sha256', 'VERIFIABLE_STATE');
    $root = $smt->update('user_x', $leafHash);

    $proof = $smt->getProof('user_x');
    assertEquals(16, count($proof), "Proof must contain depth=16 levels");

    $valid = $smt->verifyProof('user_x', $leafHash, $proof, $root);
    assertTrue($valid, "Valid proof must verify successfully against state root");

    // Negative verification: corrupted hash
    $corruptedHash = hash('sha256', 'CORRUPTED');
    $invalid = $smt->verifyProof('user_x', $corruptedHash, $proof, $root);
    assertTrue(!$invalid, "Corrupted leaf hash must fail verification");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 3: AccountLeaf Mutation and Deterministic Hashing
try {
    echo "Running Test 3: AccountLeaf State Mutation & Cryptographic Hashing... ";
    $account = new AccountLeaf('acc_001', 'PUBKEY_123', 100.50, 0, ['role' => 'admin']);
    $hash0 = $account->getHash();

    $updated = $account->withBalance(250.75)->incrementNonce();
    assertEquals(250.75, $updated->getBalance(), "Balance updated");
    assertEquals(1, $updated->getNonce(), "Nonce incremented");
    $hash1 = $updated->getHash();
    assertTrue($hash0 !== $hash1, "Hash must change when state mutates");

    $arr = $updated->toArray();
    $restored = AccountLeaf::fromArray($arr);
    assertEquals($updated->getAccountId(), $restored->getAccountId(), "Account ID preserved");
    assertEquals($updated->getBalance(), $restored->getBalance(), "Balance preserved");
    assertEquals($updated->getHash(), $restored->getHash(), "Restored hash identical");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 4: Merkle Mountain Range (MMR) Structure & Peak Bagging
try {
    echo "Running Test 4: Merkle Mountain Range (MMR) Structure & Peak Bagging... ";
    $mmr = new MerkleMountainRange();
    assertEquals(0, $mmr->getLeafCount(), "MMR initially empty");

    // Append 7 leaves (binary 7 = 4 + 2 + 1: 3 mountains of heights 2, 1, 0)
    for ($i = 0; $i < 7; $i++) {
        $mmr->append(hash('sha256', "LEAF_{$i}"));
    }

    assertEquals(7, $mmr->getLeafCount(), "Total 7 leaves");
    $mountains = $mmr->getMountainStructure();
    assertEquals(3, count($mountains), "7 leaves must produce 3 distinct mountains");
    assertEquals(4, $mountains[0]['count'], "Mountain 0 has 4 leaves");
    assertEquals(2, $mountains[1]['count'], "Mountain 1 has 2 leaves");
    assertEquals(1, $mountains[2]['count'], "Mountain 2 has 1 leaf");

    $peaks = $mmr->getPeaks();
    assertEquals(3, count($peaks), "Must have 3 mountain peaks");

    $root = $mmr->getRoot();
    assertTrue(strlen($root) === 64, "Bagged MMR root must be 64-char sha256");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 5: MMR Inclusion Proofs & Verification
try {
    echo "Running Test 5: MMR Inclusion Proof Generation & Verification... ";
    $mmr = new MerkleMountainRange();
    $hashes = [];
    for ($i = 0; $i < 11; $i++) {
        $h = hash('sha256', "TX_BATCH_{$i}");
        $hashes[$i] = $h;
        $mmr->append($h);
    }

    $root = $mmr->getRoot();

    // Verify proof for leaf 0, leaf 5, and leaf 10
    foreach ([0, 3, 5, 10] as $leafIdx) {
        $proof = $mmr->getProof($leafIdx);
        $verified = MerkleMountainRange::verifyProof($hashes[$leafIdx], $proof, $root);
        assertTrue($verified, "MMR inclusion proof for leaf {$leafIdx} must pass");

        // Negative test: tampered leaf hash
        $tampered = hash('sha256', 'TAMPERED');
        $failed = MerkleMountainRange::verifyProof($tampered, $proof, $root);
        assertTrue(!$failed, "Tampered leaf hash must fail MMR verification");
    }

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 6: StateTransition Signatures and Serialization
try {
    echo "Running Test 6: State Transition Cryptographic Signatures... ";
    $tx = new StateTransition(
        'tx_101',
        StateTransitionType::TRANSFER,
        'alice',
        'bob',
        50.0,
        0
    );

    $secret = 'secret_key_alice_99';
    $signedTx = $tx->signWithSecret($secret);
    assertTrue($signedTx->verifySignature($secret), "Signature must verify with matching secret");
    assertTrue(!$signedTx->verifySignature('wrong_secret'), "Signature must fail with wrong secret");

    $arr = $signedTx->toArray();
    $restored = StateTransition::fromArray($arr);
    assertEquals($signedTx->getTransitionId(), $restored->getTransitionId(), "Transition ID matches");
    assertEquals($signedTx->getAmount(), $restored->getAmount(), "Amount matches");
    assertTrue($restored->verifySignature($secret), "Restored transition signature valid");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 7: RollupExecutionEngine Valid Batch Execution
try {
    echo "Running Test 7: RollupExecutionEngine Execution & Attestation Proof... ";
    $engine = new RollupExecutionEngine();
    $tree = new SparseMerkleTree(16);

    $accounts = [
        'alice' => new AccountLeaf('alice', 'PK_A', 500.0, 0),
        'bob'   => new AccountLeaf('bob', 'PK_B', 100.0, 0),
    ];
    $tree->update('alice', $accounts['alice']->getHash());
    $tree->update('bob', $accounts['bob']->getHash());

    $tx1 = new StateTransition('tx_1', StateTransitionType::TRANSFER, 'alice', 'bob', 150.0, 0);
    $tx2 = new StateTransition('tx_2', StateTransitionType::MINT, '0x00', 'alice', 50.0, 0);

    $batch = $engine->executeBatch($accounts, $tree, [$tx1, $tx2], 1);

    assertEquals(400.0, $accounts['alice']->getBalance(), "Alice: 500 - 150 + 50 = 400");
    assertEquals(1, $accounts['alice']->getNonce(), "Alice nonce incremented to 1");
    assertEquals(250.0, $accounts['bob']->getBalance(), "Bob: 100 + 150 = 250");

    $proof = $batch->getValidityProof();
    assertEquals('Groth16/Plonk-Attestation', $proof['proof_scheme'], "Proof scheme correct");
    assertTrue($proof['verified'], "Proof marked verified");
    assertTrue($engine->verifyBatchProof($batch), "Engine batch proof integrity valid");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 8: RollupExecutionEngine Invariant Violations and Rejections
try {
    echo "Running Test 8: Execution Engine Invariants & Error Guardrails... ";
    $engine = new RollupExecutionEngine();
    $tree = new SparseMerkleTree(16);

    $accounts = [
        'alice' => new AccountLeaf('alice', 'PK_A', 50.0, 0),
        'bob'   => new AccountLeaf('bob', 'PK_B', 10.0, 0),
    ];
    $tree->update('alice', $accounts['alice']->getHash());
    $tree->update('bob', $accounts['bob']->getHash());

    // 8.1 Insufficient balance
    $excessTx = new StateTransition('bad_tx1', StateTransitionType::TRANSFER, 'alice', 'bob', 500.0, 0);
    $threw1 = false;
    try {
        $engine->executeBatch($accounts, $tree, [$excessTx], 2);
    } catch (InvalidStateTransitionException $e) {
        $threw1 = true;
    }
    assertTrue($threw1, "Must reject transfer exceeding balance");

    // 8.2 Nonce mismatch
    $badNonceTx = new StateTransition('bad_tx2', StateTransitionType::TRANSFER, 'alice', 'bob', 10.0, 5);
    $threw2 = false;
    try {
        $engine->executeBatch($accounts, $tree, [$badNonceTx], 3);
    } catch (InvalidStateTransitionException $e) {
        $threw2 = true;
    }
    assertTrue($threw2, "Must reject transaction with invalid nonce");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 9: RollupManager End-to-End Batching & Cross-Tree Proofs
try {
    echo "Running Test 9: RollupManager End-to-End Lifecycle & Cross-Tree Verification... ";
    $manager = new RollupManager(16);

    $manager->registerAccount('alice', null, 1000.0);
    $manager->registerAccount('bob', null, 200.0);
    $manager->registerAccount('charlie', null, 50.0);

    // Initial state inclusion
    assertTrue($manager->verifyAccountInclusion('alice'), "Alice initial inclusion verified");

    // Submit transitions
    $manager->transfer('alice', 'bob', 300.0);
    $manager->transfer('bob', 'charlie', 100.0);
    $manager->mint('charlie', 25.0);

    assertEquals(3, count($manager->getPendingTransitions()), "3 pending transitions in mempool");

    // Process batch
    $batch = $manager->processBatch();
    assertTrue($batch !== null, "Batch successfully created");
    assertEquals(RollupBatchStatus::COMMITTED, $batch->getStatus(), "Batch is committed");
    assertEquals(1, $batch->getBatchId(), "Batch ID is 1");
    assertEquals(0, count($manager->getPendingTransitions()), "Mempool drained after batch");

    // State updated
    assertEquals(700.0, $manager->getAccount('alice')->getBalance(), "Alice balance 700");
    assertEquals(400.0, $manager->getAccount('bob')->getBalance(), "Bob balance 400");
    assertEquals(175.0, $manager->getAccount('charlie')->getBalance(), "Charlie balance 175");

    // SMT Proofs after batch
    assertTrue($manager->verifyAccountInclusion('alice'), "Alice post-batch inclusion verified");
    assertTrue($manager->verifyAccountInclusion('bob'), "Bob post-batch inclusion verified");
    assertTrue($manager->verifyAccountInclusion('charlie'), "Charlie post-batch inclusion verified");

    // MMR Proof of batch
    assertTrue($manager->verifyBatchInMmr(0, $batch->getBatchHash()), "Batch 0 verified in MMR history");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 10: Global Helpers & Helper Persistence
try {
    echo "Running Test 10: Rollup Global Helper Functions... ";
    $mgr = rollup();
    assertTrue($mgr instanceof RollupManager, "rollup() returns RollupManager instance");

    $leaf = rollup_account('dave', null, 500.0);
    assertTrue($leaf instanceof AccountLeaf, "rollup_account() returns AccountLeaf");
    assertEquals('dave', $leaf->getAccountId(), "Account ID is dave");

    $leaf2 = rollup_account('dave'); // Retrieve existing
    assertEquals(500.0, $leaf2->getBalance(), "Retrieved dave balance is 500");

    rollup_account('eve', null, 100.0);
    $tx = rollup_transfer('dave', 'eve', 50.0);
    assertTrue($tx instanceof StateTransition, "rollup_transfer() returns StateTransition");

    // Process batch via singleton
    $mgr->processBatch();
    assertEquals(450.0, $mgr->getAccount('dave')->getBalance(), "Dave balance updated to 450");
    assertEquals(150.0, $mgr->getAccount('eve')->getBalance(), "Eve balance updated to 150");

    assertTrue(rollup_verify('dave'), "rollup_verify('dave') returns true");
    assertTrue(rollup_verify('eve'), "rollup_verify('eve') returns true");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "--------------------------------------------------------" . PHP_EOL;
echo "Phase 113 Test Results: {$testsPassed}/{$totalTests} Passed (" . round(($testsPassed / $totalTests) * 100, 2) . "%)" . PHP_EOL;
echo "--------------------------------------------------------" . PHP_EOL;

if ($testsPassed === $totalTests) {
    echo "SUCCESS: All Phase 113 tests passed!" . PHP_EOL;
    exit(0);
} else {
    echo "FAILURE: Some Phase 113 tests failed." . PHP_EOL;
    exit(1);
}
