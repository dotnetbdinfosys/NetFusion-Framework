<?php
/**
 * NetFusion Framework - Phase 112 Comprehensive Verification Suite
 *
 * Test suite for Cognitive Architecture (ACT-R / SOAR Engine, Episodic & Declarative Chunks).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Cognitive\CognitiveManager;
use NetFusion\Core\Cognitive\Enums\BufferType;
use NetFusion\Core\Cognitive\Enums\ImpasseType;
use NetFusion\Core\Cognitive\Enums\MemoryType;
use NetFusion\Core\Cognitive\Exceptions\ChunkRetrievalException;
use NetFusion\Core\Cognitive\Exceptions\CognitiveImpasseException;
use NetFusion\Core\Cognitive\Memory\Chunk;
use NetFusion\Core\Cognitive\Memory\DeclarativeMemory;
use NetFusion\Core\Cognitive\Memory\EpisodicMemory;
use NetFusion\Core\Cognitive\Production\ProductionRule;
use NetFusion\Core\Cognitive\Production\RuleEngine;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

echo "=== Running Phase 112: Cognitive Architecture & Synthetic Memory Suite ===\n\n";

// Test 1: MemoryType, BufferType and ImpasseType Enums
try {
    $dm = MemoryType::Declarative;
    $gb = BufferType::Goal;
    $imp = ImpasseType::NoChange;

    assertTest(
        $dm->value === 'declarative' && $gb->isWritable() && $imp->requiresSubgoal(),
        'Test 1: Cognitive Enums (MemoryType, BufferType, ImpasseType) validate architectural taxonomy'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 1 Exception: ' . $e->getMessage());
}

// Test 2: Chunk structure and slot matching
try {
    $c = new Chunk('chunk_1', 'user', ['name' => 'John', 'role' => 'admin']);
    $m1 = $c->matchScore(['role' => 'admin']);
    $m2 = $c->matchScore(['role' => 'guest']);

    assertTest(
        $c->getId() === 'chunk_1' && $c->getSlot('name') === 'John' && $m1 === 1.0 && $m2 === -1.0,
        'Test 2: Chunk encapsulates slot-value knowledge representations and performs exact pattern matching'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 2 Exception: ' . $e->getMessage());
}

// Test 3: ACT-R Base-Level Activation Power-Law Decay
try {
    $t0 = 1000.0;
    $c = new Chunk('chunk_act', 'item', ['x' => 1], $t0, 0.5);

    // Initial activation at t = 1001.0 (delta = 1.0): ln(1.0^(-0.5)) = ln(1.0) = 0.0
    $act1 = $c->getBaseLevelActivation(1001.0);

    // Rehearse 3 times at t=1002.0
    $c->touch(1002.0);
    $c->touch(1002.0);
    $c->touch(1002.0);
    $act2 = $c->getBaseLevelActivation(1002.1);

    assertTest(
        abs($act1 - 0.0) < 0.01 && $act2 > $act1 && $c->getAccessCount() === 4,
        'Test 3: Chunk calculates ACT-R power-law base-level activation A_i and increases with rehearsal access'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 3 Exception: ' . $e->getMessage());
}

// Test 4: DeclarativeMemory threshold retrieval and best-matching activation
try {
    $dm = new DeclarativeMemory(-5.0, 0.0);
    $c1 = new Chunk('c_low', 'fact', ['topic' => 'ai'], 100.0, 0.5);
    $c2 = new Chunk('c_high', 'fact', ['topic' => 'ai'], 500.0, 0.5);

    // c2 is more recent, should have higher activation at t=501.0
    $dm->addChunk($c1);
    $dm->addChunk($c2);

    $retrieved = $dm->retrieve(['topic' => 'ai'], 501.0);

    assertTest(
        $retrieved->getId() === 'c_high' && $dm->count() === 2,
        'Test 4: DeclarativeMemory retrieves highest-activation matching chunk adhering to retrieval thresholds'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 4 Exception: ' . $e->getMessage());
}

// Test 5: EpisodicMemory autobiographical logging and semantic search
try {
    $em = new EpisodicMemory();
    $em->record('ep_1', ['task' => 'login'], 'input_credentials', 'success', ['auth', 'user'], 100.0);
    $em->record('ep_2', ['task' => 'pay'], 'charge_card', 'declined', ['payment'], 105.0);

    $recent = $em->getRecent(1);
    $searchAuth = $em->search(['auth']);

    assertTest(
        $em->count() === 2 && $recent[0]['id'] === 'ep_2' && count($searchAuth) === 1 && $searchAuth[0]['id'] === 'ep_1',
        'Test 5: EpisodicMemory chronologically records autobiographical event traces and performs tag/context queries'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 5 Exception: ' . $e->getMessage());
}

// Test 6: ProductionRule pattern condition matching
try {
    $rule = new ProductionRule('TestRule', [
        'goal' => ['state' => 'init'],
    ], function (&$buffers) {
        $buffers['goal']['state'] = 'done';
        return 'success';
    }, 10.0);

    $buffersMatch = ['goal' => ['state' => 'init', 'id' => 42]];
    $buffersMismatch = ['goal' => ['state' => 'running']];

    assertTest(
        $rule->matches($buffersMatch) && !$rule->matches($buffersMismatch) && $rule->getUtility() === 10.0,
        'Test 6: ProductionRule verifies multi-buffer slot condition patterns and stores learned utility'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 6 Exception: ' . $e->getMessage());
}

// Test 7: RuleEngine conflict resolution by maximum utility
try {
    $engine = new RuleEngine();
    $dm = new DeclarativeMemory();
    $em = new EpisodicMemory();

    $engine->addRule(new ProductionRule('RuleLow', ['goal' => ['task' => 'solve']], function (&$b) {
        $b['goal']['winner'] = 'low';
        return 'low';
    }, 2.0));

    $engine->addRule(new ProductionRule('RuleHigh', ['goal' => ['task' => 'solve']], function (&$b) {
        $b['goal']['winner'] = 'high';
        return 'high';
    }, 8.0));

    $buffers = ['goal' => ['task' => 'solve']];
    $res = $engine->step($buffers, $dm, $em);

    assertTest(
        $res['fired_rule'] === 'RuleHigh' && $buffers['goal']['winner'] === 'high',
        'Test 7: RuleEngine executes conflict resolution and fires the highest-utility matching production rule'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 7 Exception: ' . $e->getMessage());
}

// Test 8: SOAR Impasse detection on unmatchable state
try {
    $engine = new RuleEngine();
    $dm = new DeclarativeMemory();
    $em = new EpisodicMemory();

    $buffers = ['goal' => ['state' => 'unrecognized_state']];
    $caughtImpasse = false;

    try {
        $engine->step($buffers, $dm, $em);
    } catch (CognitiveImpasseException) {
        $caughtImpasse = true;
    }

    assertTest(
        $caughtImpasse,
        'Test 8: RuleEngine raises CognitiveImpasseException (NoChange impasse) when no rule matches buffers'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 8 Exception: ' . $e->getMessage());
}

// Test 9: CognitiveManager full ACT-R cycle orchestration
try {
    $cm = new CognitiveManager();
    $cm->setBufferSlot('goal', 'step', 1);

    $cm->learnFact('math_fact_1', 'arithmetic', ['problem' => '2+2', 'answer' => 4]);

    $cm->defineRule('Step1_Retrieve', [
        'goal' => ['step' => 1],
    ], function (array &$buffers, $dm, $em) {
        $fact = $dm->retrieve(['problem' => '2+2']);
        $buffers['retrieval'] = $fact->getSlots();
        $buffers['goal']['step'] = 2;
        return 'retrieved';
    }, 5.0);

    $cm->defineRule('Step2_Conclude', [
        'goal' => ['step' => 2],
    ], function (array &$buffers, $dm, $em) {
        $buffers['action']['result'] = $buffers['retrieval']['answer'];
        $buffers['goal']['step'] = 3;
        return 'concluded';
    }, 5.0);

    $history = $cm->runCycles(3);

    assertTest(
        count($history) === 3 &&
        $history[0]['fired_rule'] === 'Step1_Retrieve' &&
        $history[1]['fired_rule'] === 'Step2_Conclude' &&
        $cm->getBufferSlot('action', 'result') === 4,
        'Test 9: CognitiveManager coordinates declarative recall, working buffers, and multi-cycle execution'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 9 Exception: ' . $e->getMessage());
}

// Test 10: Global helper functions and retrieval failure exception
try {
    $mgr = cognitive();
    $chunk = cognitive_chunk('chunk_helper', 'vocab', ['word' => 'antigravity']);
    $recalled = cognitive_recall(['word' => 'antigravity']);

    $caughtBelowThresh = false;
    try {
        // Query non-existent
        cognitive_recall(['word' => 'nonexistent_key_xyz']);
    } catch (ChunkRetrievalException) {
        $caughtBelowThresh = true;
    }

    assertTest(
        $mgr instanceof CognitiveManager &&
        $recalled->getId() === 'chunk_helper' &&
        $caughtBelowThresh,
        'Test 10: Global helpers cognitive(), cognitive_chunk(), cognitive_recall() operate cleanly with IoC'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 10 Exception: ' . $e->getMessage());
}

echo "\nVerification Summary: {$passed} Passed, {$failed} Failed\n";
exit($failed === 0 ? 0 : 1);
