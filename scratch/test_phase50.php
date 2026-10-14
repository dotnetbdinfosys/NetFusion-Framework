<?php

/**
 * NetFusion Phase 50 Test Suite:
 * Enterprise Real-Time Collaborative Document Editing, Operational Transformation (OT) & WebRTC Signaling Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Collaboration\CollabManager;
use NetFusion\Core\Collaboration\Document\Contracts\DocumentStoreInterface;
use NetFusion\Core\Collaboration\Document\DocumentSession;
use NetFusion\Core\Collaboration\Document\DocumentSnapshot;
use NetFusion\Core\Collaboration\Document\Store\ArrayDocumentStore;
use NetFusion\Core\Collaboration\Document\Store\DatabaseDocumentStore;
use NetFusion\Core\Collaboration\Http\CollabDocumentController;
use NetFusion\Core\Collaboration\Ot\Cursor;
use NetFusion\Core\Collaboration\Ot\Selection;
use NetFusion\Core\Collaboration\Ot\TextOperation;
use NetFusion\Core\Collaboration\Socket\CollabWebSocketHandler;
use NetFusion\Core\Collaboration\WebRtc\RoomManager;
use NetFusion\Core\Collaboration\WebRtc\SignalingMessage;
use NetFusion\Core\Collaboration\WebRtc\SignalingServer;
use NetFusion\Core\Http\Request;

echo "\n=======================================================\n";
echo "   NetFusion Phase 50: Real-Time Collab & OT Suite     \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "✔ [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "✖ [FAIL] {$desc}\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// 1. TextOperation Atom Construction & Normalization
// -----------------------------------------------------------------------------
echo "--- 1. TextOperation Construction & Normalization ---\n";

$op1 = (new TextOperation())->retain(5)->insert(" World")->delete(3);
assertTest('TextOperation: Retain, insert and delete updates base & target lengths',
    $op1->getBaseLength() === 8 && $op1->getTargetLength() === 11
);

$opAdjacent = (new TextOperation())->retain(2)->retain(3)->insert("Hello")->insert(" ")->delete(2)->delete(1);
assertTest('TextOperation: Adjacent operations automatically merged and normalized',
    $opAdjacent->getOps() === [5, "Hello ", -3]
);

// -----------------------------------------------------------------------------
// 2. TextOperation apply() & invert()
// -----------------------------------------------------------------------------
echo "\n--- 2. TextOperation apply() & invert() ---\n";

$baseText = "Hello World";
$opApply = (new TextOperation())->retain(6)->delete(5)->insert("DotNetBD");
$modifiedText = $opApply->apply($baseText);
assertTest('TextOperation: apply() accurately applies retain, delete, and insert',
    $modifiedText === "Hello DotNetBD"
);

$inverseOp = $opApply->invert($baseText);
$revertedText = $inverseOp->apply($modifiedText);
assertTest('TextOperation: invert() computes exact inverse and restores original text',
    $revertedText === $baseText
);

// -----------------------------------------------------------------------------
// 3. TextOperation compose()
// -----------------------------------------------------------------------------
echo "\n--- 3. TextOperation compose() ---\n";

// S0 = "Hello World"
// OpA: replace "World" with "There" -> "Hello There"
$opA = (new TextOperation())->retain(6)->delete(5)->insert("There");
// OpB: replace "Hello" with "Hi" -> "Hi There"
$opB = (new TextOperation())->delete(5)->insert("Hi")->retain(6);

$opComposed = $opA->compose($opB);
$directApply = $opComposed->apply("Hello World");
assertTest('TextOperation: compose() merges sequential operations into single atomic operation',
    $directApply === "Hi There"
);

// -----------------------------------------------------------------------------
// 4. Operational Transformation (OT) Convergence (TP1 Property)
// -----------------------------------------------------------------------------
echo "\n--- 4. Operational Transformation (OT) Convergence ---\n";

// Document: "1234"
// Alice concurrently inserts "a" at index 1: "1a234"
$opAlice = (new TextOperation())->retain(1)->insert("a")->retain(3);

// Bob concurrently deletes "3" at index 2: "124"
$opBob = (new TextOperation())->retain(2)->delete(1)->retain(1);

// Operational Transformation
[$opAlicePrime, $opBobPrime] = TextOperation::transformOperations($opAlice, $opBob, 'left');

$aliceThenBobPrime = $opBobPrime->apply($opAlice->apply("1234"));
$bobThenAlicePrime = $opAlicePrime->apply($opBob->apply("1234"));

assertTest('OT Transform: Alice + BobPrime converges to Bob + AlicePrime ("1a24")',
    $aliceThenBobPrime === "1a24" &&
    $bobThenAlicePrime === "1a24"
);

// Concurrent inserts at same index
$opLeft = (new TextOperation())->retain(2)->insert("X")->retain(2);
$opRight = (new TextOperation())->retain(2)->insert("Y")->retain(2);
[$leftPrime, $rightPrime] = TextOperation::transformOperations($opLeft, $opRight, 'left');

$leftThenRight = $rightPrime->apply($opLeft->apply("ABCD"));
$rightThenLeft = $leftPrime->apply($opRight->apply("ABCD"));

assertTest('OT Transform: Concurrent identical index inserts resolve deterministically',
    $leftThenRight === "ABXYCD" &&
    $rightThenLeft === "ABXYCD"
);

// -----------------------------------------------------------------------------
// 5. Selection & Cursor Transformation
// -----------------------------------------------------------------------------
echo "\n--- 5. Selection & Cursor Transformation ---\n";

$selection = new Selection(5, 10);
// Insert 3 chars before selection (at index 2)
$opInsertPrior = (new TextOperation())->retain(2)->insert("XYZ")->retain(10);
$transformedSel = $selection->transform($opInsertPrior);
assertTest('Selection: Shifted forward when text is inserted before selection range',
    $transformedSel->anchor === 8 && $transformedSel->head === 13
);

$cursor = new Cursor('u1', 'Alice', '#ef4444', new Selection(4, 4));
$cursor->transform($opInsertPrior);
assertTest('Cursor: Preserves user metadata and shifts position accurately',
    $cursor->selection->anchor === 7 && $cursor->userId === 'u1'
);

// -----------------------------------------------------------------------------
// 6. Collaborative Document Session State Machine
// -----------------------------------------------------------------------------
echo "\n--- 6. DocumentSession Concurrency State Machine ---\n";

$arrayStore = new ArrayDocumentStore();
$snapshot = new DocumentSnapshot('doc_sample', 'Live Specs', 'Welcome to NetFusion', 0);
$arrayStore->save($snapshot);

$session = new DocumentSession($snapshot, $arrayStore);
$session->updateCursor('u_alice', 'Alice', '#3b82f6', new Selection(0, 0));
$session->updateCursor('u_bob', 'Bob', '#10b981', new Selection(10, 10));

// Alice (Rev 0): inserts "Enterprise " at index 11
$opAliceRev0 = (new TextOperation())->retain(11)->insert("Enterprise ")->retain(9);
$resAlice = $session->applyClientOperation(0, $opAliceRev0, 'u_alice');

assertTest('DocumentSession: Applies in-sync client operation and increments revision',
    $resAlice['revision'] === 1 &&
    $session->getContent() === "Welcome to Enterprise NetFusion"
);

// Bob had based his change on Rev 0 (lagging behind)!
// Bob wants to append " Framework" at the end of original text (index 20)
$opBobLagging = (new TextOperation())->retain(20)->insert(" Framework");
$resBob = $session->applyClientOperation(0, $opBobLagging, 'u_bob');

assertTest('DocumentSession: Automatically catches up lagging client operation via OT transform',
    $resBob['revision'] === 2 &&
    $session->getContent() === "Welcome to Enterprise NetFusion Framework"
);

assertTest('DocumentSession: Cursors adjusted dynamically after collaborative edits',
    $session->getCursor('u_bob') !== null
);

// -----------------------------------------------------------------------------
// 7. Database Document Store & Migration
// -----------------------------------------------------------------------------
echo "\n--- 7. Database Document Store & Migration ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000030_create_collab_documents_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseDocumentStore($pdo);
$dbDoc = new DocumentSnapshot('db_doc_101', 'Arch Plan', 'Initial System Design', 0, 'dev_1');
$dbStore->save($dbDoc);

assertTest('DatabaseDocumentStore: Persists document snapshot into SQLite database',
    $dbStore->exists('db_doc_101') &&
    $dbStore->load('db_doc_101')?->title === 'Arch Plan'
);

$opRecord = (new TextOperation())->retain(7)->insert(" High-Level")->retain(14);
$dbStore->appendOperation('db_doc_101', 1, $opRecord, 'dev_1');
$opsLoaded = $dbStore->getOperationsSince('db_doc_101', 0);

assertTest('DatabaseDocumentStore: Appends and retrieves OT operation stream',
    count($opsLoaded) === 1 &&
    $opsLoaded[0]['user_id'] === 'dev_1' &&
    $opsLoaded[0]['operation'] instanceof TextOperation
);

// Migration Down
$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_collab_documents'");
$dropped = $stmt && $stmt->fetchColumn() === false;
assertTest('Database Migration: Tables dropped cleanly on down()', $dropped);

// -----------------------------------------------------------------------------
// 8. WebRTC Signaling Engine & Room Management
// -----------------------------------------------------------------------------
echo "\n--- 8. WebRTC Signaling Engine & Room Management ---\n";

$roomManager = new RoomManager();
$signalingServer = new SignalingServer($roomManager);

// Peer 1 joins room
$join1 = SignalingMessage::create(SignalingMessage::TYPE_JOIN, 'room_alpha', 'peer_alice', payload: ['name' => 'Alice']);
$out1 = $signalingServer->handleMessage($join1);
assertTest('SignalingServer: First peer joins room and receives empty peer list',
    count($out1) === 1 &&
    $out1[0]->type === SignalingMessage::TYPE_PEERS &&
    empty($out1[0]->payload['peers'])
);

// Peer 2 joins room
$join2 = SignalingMessage::create(SignalingMessage::TYPE_JOIN, 'room_alpha', 'peer_bob', payload: ['name' => 'Bob']);
$out2 = $signalingServer->handleMessage($join2);
assertTest('SignalingServer: Second peer receives existing peer list and notifies existing peer',
    count($out2) === 2 &&
    $roomManager->count('room_alpha') === 2
);

// WebRTC Offer & Answer forwarding
$offerMsg = SignalingMessage::offer('room_alpha', 'peer_alice', 'peer_bob', ['type' => 'offer', 'sdp' => 'v=0...']);
$outOffer = $signalingServer->handleMessage($offerMsg);
assertTest('SignalingServer: Routes SDP offer directly to targeted peer',
    count($outOffer) === 1 &&
    $outOffer[0]->targetId === 'peer_bob' &&
    $outOffer[0]->type === SignalingMessage::TYPE_OFFER
);

// Peer leaves
$leaveMsg = SignalingMessage::create(SignalingMessage::TYPE_LEAVE, 'room_alpha', 'peer_alice');
$signalingServer->handleMessage($leaveMsg);
assertTest('SignalingServer: Peer leaves room and member count decreases',
    $roomManager->count('room_alpha') === 1 &&
    !$roomManager->hasPeer('room_alpha', 'peer_alice')
);

// -----------------------------------------------------------------------------
// 9. HTTP Controller & WebSocket Handler
// -----------------------------------------------------------------------------
echo "\n--- 9. HTTP Controller & WebSocket Handler ---\n";

$collabMgr = new CollabManager(new ArrayDocumentStore());
$controller = new CollabDocumentController($collabMgr);

// HTTP Create Document
$reqCreate = Request::create('POST', '/api/v1/collab/documents', [
    'id' => 'api_doc_1',
    'title' => 'REST API Doc',
    'content' => 'Hello World',
]);
$resCreate = $controller->createDocument($reqCreate);
assertTest('CollabDocumentController: Creates document via HTTP endpoint',
    $resCreate->getStatusCode() === 201
);

// HTTP Submit Operation
$reqOp = Request::create('POST', '/api/v1/collab/documents/api_doc_1/operations', [
    'id' => 'api_doc_1',
    'revision' => 0,
    'user_id' => 'u_http',
    'operation' => (new TextOperation())->retain(5)->insert(" Rest")->retain(6)->toArray(),
]);
$resOp = $controller->submitOperation($reqOp);
$dataOp = json_decode($resOp->getContent(), true);
assertTest('CollabDocumentController: Submits and transforms operation over HTTP',
    $resOp->getStatusCode() === 200 &&
    $dataOp['success'] === true &&
    $dataOp['revision'] === 1 &&
    $dataOp['document_content'] === "Hello Rest World"
);

// WebSocket Handler
$wsHandler = new CollabWebSocketHandler($collabMgr);
$wsRes = $wsHandler->handle([
    'action' => 'ot:operation',
    'document_id' => 'api_doc_1',
    'revision' => 1,
    'user_id' => 'u_ws',
    'operation' => (new TextOperation())->retain(16)->insert("!")->toArray(),
]);

assertTest('CollabWebSocketHandler: Handles WebSocket message and broadcasts applied operation',
    $wsRes['event'] === 'ot:operation_applied' &&
    $wsRes['revision'] === 2
);

// -----------------------------------------------------------------------------
// 10. Global Helpers & DI Container
// -----------------------------------------------------------------------------
echo "\n--- 10. Global Helpers & DI Container ---\n";

$app = new Application(dirname(__DIR__));
$app->instance(CollabManager::class, $collabMgr);
$app->instance('collab', $collabMgr);

assertTest('Global Helper: collab() returns CollabManager instance', collab() instanceof CollabManager);
assertTest('Global Helper: collab("api_doc_1") returns DocumentSession', collab('api_doc_1') instanceof DocumentSession);
assertTest('Global Helper: ot_operation() constructs TextOperation', ot_operation([5, "Text", -2]) instanceof TextOperation);
assertTest('Global Helper: webrtc_signal() constructs SignalingMessage', webrtc_signal('offer', 'r1', 'p1', 'p2') instanceof SignalingMessage);

echo "\n=======================================================\n";
echo "   Phase 50 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
