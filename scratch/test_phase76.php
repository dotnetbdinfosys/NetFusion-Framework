<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Collaboration\CRDT\CharId;
use NetFusion\Core\Collaboration\CRDT\RgaNode;
use NetFusion\Core\Collaboration\CRDT\RgaText;
use NetFusion\Core\Collaboration\CRDT\LwwMap;
use NetFusion\Core\Collaboration\CRDT\PnCounter;
use NetFusion\Core\Collaboration\OT\TextOperation;
use NetFusion\Core\Collaboration\OT\OperationalTransform;
use NetFusion\Core\Collaboration\OT\RevisionHistory;
use NetFusion\Core\Collaboration\WebRTC\SdpMessage;
use NetFusion\Core\Collaboration\WebRTC\IceCandidate;
use NetFusion\Core\Collaboration\WebRTC\SignalingRoom;
use NetFusion\Core\Collaboration\WebRTC\PeerMesh;
use NetFusion\Core\Collaboration\Session\CursorPresence;
use NetFusion\Core\Collaboration\Session\DocumentSnapshot;
use NetFusion\Core\Collaboration\Session\CollaborativeDocument;
use NetFusion\Core\Collaboration\CollaborationManager;
use NetFusion\Core\Console\Commands\CollabSessionCommand;
use NetFusion\Core\Console\Commands\CollabPruneCommand;

$app = new Application(realpath(__DIR__ . '/..'));

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [\033[32mPASS\033[0m] {$testName}\n";
    } else {
        $failed++;
        echo "  [\033[31mFAIL\033[0m] {$testName}\n";
    }
}

echo "\n\033[1;36m=== NetFusion Phase 76: Real-Time Collaborative CRDT, WebRTC Mesh & OT Engine Test Suite ===\033[0m\n\n";

// ==========================================
// 1. Lamport Timestamp & CharId
// ==========================================
echo "\033[1;33m[1. Lamport Timestamp & CharId]\033[0m\n";

$id1 = new CharId(10, 'peer-A', 1);
$id2 = new CharId(10, 'peer-B', 1);
$id3 = new CharId(12, 'peer-A', 1);

assertTest($id1->compareTo($id3) < 0, 'CharId orders by clock timestamp first');
assertTest($id1->compareTo($id2) < 0, 'CharId orders by peerId lexicographically on clock collision');
assertTest(CharId::fromString($id1->toString())->compareTo($id1) === 0, 'CharId serializes to string and parses accurately');

// ==========================================
// 2. RGA Text CRDT (Replicated Growable Array)
// ==========================================
echo "\n\033[1;33m[2. RgaText CRDT]\033[0m\n";

$docAlice = new RgaText('alice');
$docAlice->insert(0, "Hello World");
assertTest($docAlice->getText() === "Hello World" && $docAlice->length() === 11, 'RgaText inserts characters correctly on single peer');

$docAlice->delete(5, 6); // Delete " World"
assertTest($docAlice->getText() === "Hello", 'RgaText marks nodes as deleted tombstone and shrinks visible length');

// Concurrent Editing Simulation between Alice and Bob
$peerAlice = new RgaText('alice');
$peerAlice->insert(0, "ABC");

$peerBob = new RgaText('bob');
$peerBob->merge($peerAlice);
assertTest($peerBob->getText() === "ABC", 'RgaText initial state synchronizes across peers');

// Alice inserts 'X' between B and C (visible index 2)
$nodesAlice = $peerAlice->insert(2, "X");

// Bob inserts 'Y' at same slot (visible index 2)
$nodesBob = $peerBob->insert(2, "Y");

// Cross-merge
$peerAlice->merge($peerBob);
$peerBob->merge($peerAlice);

assertTest($peerAlice->getText() === $peerBob->getText(), 'RgaText converges to identical text under concurrent inserts');
assertTest(str_contains($peerAlice->getText(), 'X') && str_contains($peerAlice->getText(), 'Y'), 'RgaText preserves all concurrent inserted characters');

// ==========================================
// 3. LWW-Map CRDT (Last-Write-Wins Map)
// ==========================================
echo "\n\033[1;33m[3. LwwMap CRDT]\033[0m\n";

$mapA = new LwwMap('peer-A');
$mapB = new LwwMap('peer-B');

$t0 = 1000.0;
$mapA->set('title', 'Draft V1', $t0);
$mapB->set('title', 'Draft V2', $t0 + 5.0); // Later timestamp
$mapA->set('theme', 'dark', $t0 + 2.0);

$mapA->merge($mapB);
$mapB->merge($mapA);

assertTest($mapA->get('title') === 'Draft V2' && $mapB->get('title') === 'Draft V2', 'LwwMap resolves concurrent updates using highest timestamp');
assertTest($mapA->get('theme') === 'dark' && $mapB->get('theme') === 'dark', 'LwwMap merges disjoint keys seamlessly');

$mapA->delete('theme', $t0 + 10.0);
$mapB->merge($mapA);
assertTest(!$mapB->has('theme') && $mapB->get('theme') === null, 'LwwMap propagates deleted tombstones on higher timestamp');

// ==========================================
// 4. PN-Counter CRDT (Positive-Negative Counter)
// ==========================================
echo "\n\033[1;33m[4. PnCounter CRDT]\033[0m\n";

$cnt1 = new PnCounter('peer-1');
$cnt2 = new PnCounter('peer-2');

$cnt1->increment(10);
$cnt1->decrement(3); // net 7

$cnt2->increment(5);
$cnt2->decrement(2); // net 3

$cnt1->merge($cnt2);
$cnt2->merge($cnt1);

assertTest($cnt1->value() === 10 && $cnt2->value() === 10, 'PnCounter converges across concurrent increments and decrements');

// ==========================================
// 5. Operational Transformation (OT) TextOperation
// ==========================================
echo "\n\033[1;33m[5. OT TextOperation]\033[0m\n";

$baseText = "NetFusion";
$op1 = (new TextOperation())->retain(9)->insert(" Framework");
assertTest($op1->apply($baseText) === "NetFusion Framework", 'TextOperation applies retain and insert accurately');

$opDelete = (new TextOperation())->retain(3)->delete(6); // delete "Fusion"
assertTest($opDelete->apply("NetFusion") === "Net", 'TextOperation applies retain and delete');

// Compose: "Net" -> "Network" -> "Networking"
$comp1 = (new TextOperation())->retain(3)->insert("work"); // "Network"
$comp2 = (new TextOperation())->retain(7)->insert("ing");  // "Networking"
$composed = $comp1->compose($comp2);
assertTest($composed->apply("Net") === "Networking", 'TextOperation composes sequential operations into a single atomic delta');

// ==========================================
// 6. Operational Transformation (OT) Convergence Matrix
// ==========================================
echo "\n\033[1;33m[6. Operational Transformation Matrix]\033[0m\n";

// Base doc: "ABCD" (length 4)
// Client A inserts "X" after B -> "ABXCD" (retain 2, insert "X", retain 2)
$opA = (new TextOperation())->retain(2)->insert("X")->retain(2);

// Client B deletes "C" -> "ABD" (retain 2, delete 1, retain 1)
$opB = (new TextOperation())->retain(2)->delete(1)->retain(1);

[$opAPrime, $opBPrime] = OperationalTransform::transform($opA, $opB);

$res1 = $opBPrime->apply($opA->apply("ABCD")); // Apply A then B'
$res2 = $opAPrime->apply($opB->apply("ABCD")); // Apply B then A'

assertTest($res1 === $res2 && $res1 === "ABXD", 'OperationalTransform satisfies TP1 convergence property for concurrent insert and delete');

// ==========================================
// 7. RevisionHistory & Server Rebasing
// ==========================================
echo "\n\033[1;33m[7. Server-Side RevisionHistory & Rebasing]\033[0m\n";

$serverHistory = new RevisionHistory("Hello");

// Client 1 commits against rev 0: insert " World" -> "Hello World"
$c1Op = (new TextOperation())->retain(5)->insert(" World");
$commit1 = $serverHistory->commit(0, $c1Op, 'client-1');
assertTest($commit1['revision'] === 1 && $serverHistory->getDocument() === "Hello World", 'RevisionHistory commits fresh client operation');

// Client 2 was offline and originated an op from rev 0: insert "!" at index 5 -> "Hello!"
$c2Op = (new TextOperation())->retain(5)->insert("!");
// Server rebases c2Op from rev 0 to current rev 1 (transformed to insert "!" before " World")
$commit2 = $serverHistory->commit(0, $c2Op, 'client-2');
assertTest($commit2['revision'] === 2 && $serverHistory->getDocument() === "Hello! World", 'RevisionHistory rebases stale client op and resolves to converged text');

// ==========================================
// 8. WebRTC Signaling & SDP/ICE Messages
// ==========================================
echo "\n\033[1;33m[8. WebRTC Signaling Room & Messages]\033[0m\n";

$sdpOffer = new SdpMessage('offer', 'v=0\r\no=alice ...', 'alice', 'bob');
assertTest($sdpOffer->isOffer() && !$sdpOffer->isAnswer(), 'SdpMessage identifies offer payload');

$ice = new IceCandidate('candidate:1 1 UDP 2130706431 192.168.1.1 50000 typ host', '0', 0, 'alice', 'bob');
assertTest($ice->fromPeer === 'alice' && $ice->toPeer === 'bob', 'IceCandidate encapsulates NAT traversal candidate');

$room = new SignalingRoom('room-project-x');
$room->join('alice', ['name' => 'Alice']);
$room->join('bob', ['name' => 'Bob']);

assertTest($room->getPeerCount() === 2 && $room->hasPeer('alice'), 'SignalingRoom tracks joined peers');

$room->sendSdp($sdpOffer);
$bobMessages = $room->pollMessages('bob');
assertTest(count($bobMessages) === 1 && $bobMessages[0]['type'] === 'sdp', 'SignalingRoom routes SDP message to targeted peer inbox');

$emptyPoll = $room->pollMessages('bob');
assertTest(empty($emptyPoll), 'SignalingRoom drains polled messages atomically');

// ==========================================
// 9. PeerMesh Topology & Connection Metrics
// ==========================================
echo "\n\033[1;33m[9. WebRTC PeerMesh Topology]\033[0m\n";

$mesh = new PeerMesh();
$mesh->setChannelState('peer-1', 'peer-2', 'connected', 25.5);
$mesh->setChannelState('peer-2', 'peer-3', 'connected', 35.0);
$mesh->setChannelState('peer-1', 'peer-3', 'connected', 40.0);

$metrics = $mesh->getMeshMetrics();
assertTest($metrics['peerCount'] === 3, 'PeerMesh tracks 3 distinct peers in mesh');
assertTest($metrics['activeConnectedChannels'] === 3, 'PeerMesh tracks full triangular connectivity');
assertTest((float)$metrics['meshDensity'] === 1.0, 'PeerMesh calculates 100% full-mesh network density');

// ==========================================
// 10. Cursor Presence & Awareness
// ==========================================
echo "\n\033[1;33m[10. Cursor Presence & Awareness]\033[0m\n";

$presence = new CursorPresence('peer-ashish', 'Ashish', 15, 20, '#10b981');
assertTest($presence->isAlive(10.0), 'CursorPresence confirms active heartbeat');

$presence->updateCursor(25, 25);
assertTest($presence->cursorIndex === 25 && $presence->selectionEnd === 25, 'CursorPresence updates cursor position and selection');

// ==========================================
// 11. Document Snapshot & Checksum
// ==========================================
echo "\n\033[1;33m[11. Document Snapshot & Cryptographic Checksum]\033[0m\n";

$snapshot = new DocumentSnapshot('doc-101', 5, 'NetFusion Engine', ['author' => 'DotNetBD']);
assertTest($snapshot->verifyChecksum(), 'DocumentSnapshot calculates and verifies SHA-256 integrity checksum');

// ==========================================
// 12. Unified CollaborativeDocument
// ==========================================
echo "\n\033[1;33m[12. CollaborativeDocument Unified Model]\033[0m\n";

$collabDoc1 = new CollaborativeDocument('doc-test', 'peer-1', 'First line');
$collabDoc1->insertText(10, "\nSecond line");
$collabDoc1->setProperty('status', 'in_review');
$collabDoc1->updatePresence('peer-1', 'Editor 1', 12);

assertTest($collabDoc1->getText() === "First line\nSecond line", 'CollaborativeDocument integrates CRDT text updates');
assertTest($collabDoc1->getProperty('status') === 'in_review', 'CollaborativeDocument stores and retrieves metadata');
assertTest(count($collabDoc1->getActivePresences()) === 1, 'CollaborativeDocument tracks active peer presences');

$docSnap = $collabDoc1->createSnapshot();
assertTest($docSnap->verifyChecksum() && $docSnap->text === $collabDoc1->getText(), 'CollaborativeDocument generates verifiable snapshot');

$collabDoc2 = new CollaborativeDocument('doc-test', 'peer-2');
$collabDoc2->restoreFromSnapshot($docSnap);
assertTest($collabDoc2->getText() === $collabDoc1->getText() && $collabDoc2->getProperty('status') === 'in_review', 'CollaborativeDocument restores state from snapshot accurately');

// ==========================================
// 13. CollaborationManager Master Facade & Global Helpers
// ==========================================
echo "\n\033[1;33m[13. CollaborationManager Facade & Helpers]\033[0m\n";

$mgr = collaboration();
assertTest($mgr instanceof CollaborationManager, 'collaboration() helper returns CollaborationManager singleton');

$docGlobal = collab_doc('doc-arch', 'peer-lead', 'Architecture Blueprint');
assertTest($docGlobal instanceof CollaborativeDocument && $docGlobal->getText() === 'Architecture Blueprint', 'collab_doc() helper creates and manages document');

$roomGlobal = collab_room('sprint-planning');
assertTest($roomGlobal instanceof SignalingRoom && $roomGlobal->getRoomId() === 'sprint-planning', 'collab_room() helper resolves signaling room');

// ==========================================
// 14. CLI Commands Execution
// ==========================================
echo "\n\033[1;33m[14. CLI Commands Execution]\033[0m\n";

$sessionCmd = new CollabSessionCommand($app);
ob_start();
$sessionExit = $sessionCmd->handle(['doc-cli-test', '--peer=peer-cli', '--text=Hello Collaboration']);
$sessionOut = ob_get_clean();
assertTest($sessionExit === 0 && str_contains($sessionOut, 'Hello Collaboration'), 'CollabSessionCommand inspects and initializes collaborative session');

$pruneCmd = new CollabPruneCommand($app);
ob_start();
$pruneExit = $pruneCmd->handle(['--timeout=0']);
$pruneOut = ob_get_clean();
assertTest($pruneExit === 0 && str_contains($pruneOut, 'Cleanup complete'), 'CollabPruneCommand prunes stale peer presences from CLI');

// ==========================================
// Test Results Summary
// ==========================================
echo "\n\033[1;36m=================================================================\033[0m\n";
echo "Total Tests Run: " . ($passed + $failed) . "\n";
echo "\033[32mPassed: {$passed}\033[0m\n";
if ($failed > 0) {
    echo "\033[31mFailed: {$failed}\033[0m\n";
}
echo "\033[1;36m=================================================================\033[0m\n";

exit($failed > 0 ? 1 : 0);
