<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Phase 88 WebRTC Media Server & SFU Test Suite
 * |--------------------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Media\ABR\CongestionController;
use NetFusion\Core\Media\ABR\HlsSegmenter;
use NetFusion\Core\Media\ABR\NalUnit;
use NetFusion\Core\Media\Enums\MediaType;
use NetFusion\Core\Media\Enums\RtcpType;
use NetFusion\Core\Media\Enums\SimulcastLayer;
use NetFusion\Core\Media\Exceptions\PeerNotFoundException;
use NetFusion\Core\Media\Exceptions\RoomNotFoundException;
use NetFusion\Core\Media\MediaServerManager;
use NetFusion\Core\Media\Rtp\JitterBuffer;
use NetFusion\Core\Media\Rtp\RtcpPacket;
use NetFusion\Core\Media\Rtp\RtpPacket;
use NetFusion\Core\Media\Rtp\SrtpContext;
use NetFusion\Core\Media\SFU\MediaTrack;
use NetFusion\Core\Media\SFU\PeerConnection;
use NetFusion\Core\Media\SFU\SfuRoom;
use NetFusion\Core\Media\SFU\SimulcastRouter;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void
{
    global $passed, $failed;
    if ($condition) {
        echo "[\033[32mPASS\033[0m] {$testName}\n";
        $passed++;
    } else {
        echo "[\033[31mFAIL\033[0m] {$testName}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo " NetFusion Phase 88: WebRTC Media Server & SFU Test Suite\n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------------
// 1. RTP Packet Processing (RFC 3550)
// -----------------------------------------------------------------------------
$rawPayload = "VIDEO_FRAME_DATA_12345";
$rtp = new RtpPacket(
    payloadType: 96,
    sequenceNumber: 1042,
    timestamp: 3456789,
    ssrc: 0x12345678,
    payload: $rawPayload,
    marker: true,
    csrcList: [0x01, 0x02],
    extensionHeader: "\xBE\xDE\x00\x01\x10\x00\x00\x00"
);

$binaryRtp = $rtp->toBinary();
assertTest(strlen($binaryRtp) > 12, "RtpPacket serializes to standard RFC 3550 binary packet");

$parsedRtp = RtpPacket::parse($binaryRtp);
assertTest($parsedRtp->getPayloadType() === 96, "RtpPacket parsed payload type matches (96)");
assertTest($parsedRtp->getSequenceNumber() === 1042, "RtpPacket parsed sequence number matches (1042)");
assertTest($parsedRtp->getTimestamp() === 3456789, "RtpPacket parsed timestamp matches (3456789)");
assertTest($parsedRtp->getSsrc() === 0x12345678, "RtpPacket parsed SSRC matches (0x12345678)");
assertTest($parsedRtp->isMarker() === true, "RtpPacket parsed marker bit is set");
assertTest($parsedRtp->getPayload() === $rawPayload, "RtpPacket payload intact after binary roundtrip");
assertTest(count($parsedRtp->getCsrcList()) === 2, "RtpPacket CSRC list correctly parsed");
assertTest($parsedRtp->hasExtension() === true, "RtpPacket header extension detected");

// -----------------------------------------------------------------------------
// 2. RTCP Feedback Packets (SR, RR, PLI, NACK, REMB)
// -----------------------------------------------------------------------------
// Picture Loss Indication (PLI)
$pli = RtcpPacket::createPli(mediaSsrc: 0x99887766, senderSsrc: 0x11223344);
$pliBinary = $pli->toBinary();
$parsedPli = RtcpPacket::parse($pliBinary);
assertTest($parsedPli->getType() === RtcpType::PSFB, "PLI packet type is PSFB (206)");
assertTest($parsedPli->getFeedbackFormat() === 1, "PLI feedback format is 1");
assertTest($parsedPli->isPli() === true, "isPli() helper returns true");

// NACK (Negative Acknowledgment)
$nack = RtcpPacket::createNack(senderSsrc: 0x11223344, mediaSsrc: 0x99887766, lostSequenceNumbers: [1005, 1006]);
$parsedNack = RtcpPacket::parse($nack->toBinary());
assertTest($parsedNack->getType() === RtcpType::RTPFB, "NACK packet type is RTPFB (205)");
assertTest($parsedNack->getFeedbackFormat() === 1, "NACK feedback format is 1 (Generic NACK)");

// REMB (Receiver Estimated Maximum Bitrate)
$remb = RtcpPacket::createRemb(bitrateBps: 2500000, ssrcs: [0x99887766], senderSsrc: 0x11223344);
$parsedRemb = RtcpPacket::parse($remb->toBinary());
assertTest($parsedRemb->getType() === RtcpType::PSFB, "REMB packet type is PSFB (206)");
assertTest($parsedRemb->getFeedbackFormat() === 15, "REMB feedback format is 15 (Application layer FB)");
assertTest(str_contains($parsedRemb->getPayload(), 'REMB'), "REMB payload contains unique 4-character identifier");

// -----------------------------------------------------------------------------
// 3. JitterBuffer (Packet Reordering & Loss Detection)
// -----------------------------------------------------------------------------
$jb = new JitterBuffer(capacity: 20);
// Push out of order: seq 102, 100, 101
$jb->push(new RtpPacket(96, 102, 3000, 1, 'P3'));
$jb->push(new RtpPacket(96, 100, 1000, 1, 'P1'));
$jb->push(new RtpPacket(96, 101, 2000, 1, 'P2'));
// Duplicate push: seq 101
$dupAccepted = $jb->push(new RtpPacket(96, 101, 2000, 1, 'P2_DUP'));
assertTest($dupAccepted === false, "JitterBuffer discards duplicate sequence numbers");
assertTest($jb->size() === 3, "JitterBuffer contains 3 distinct packets");

// Pop ordered packets
$p1 = $jb->pop();
$p2 = $jb->pop();
$p3 = $jb->pop();
assertTest($p1 !== null && $p1->getSequenceNumber() === 100, "JitterBuffer correctly orders sequence #100 first");
assertTest($p2 !== null && $p2->getSequenceNumber() === 101, "JitterBuffer correctly orders sequence #101 second");
assertTest($p3 !== null && $p3->getSequenceNumber() === 102, "JitterBuffer correctly orders sequence #102 third");

// Missing sequence gap detection
$jb->flush();
$jb->push(new RtpPacket(96, 200, 10000, 1, 'A'));
$jb->push(new RtpPacket(96, 203, 13000, 1, 'D'));
$missing = $jb->detectMissingSequences();
assertTest($missing === [201, 202], "JitterBuffer detects missing sequence gaps [201, 202] for NACK request");

// -----------------------------------------------------------------------------
// 4. SRTP Payload Encryption & Authentication (RFC 3711)
// -----------------------------------------------------------------------------
$masterKey = random_bytes(16);
$masterSalt = random_bytes(14);
$srtp = new SrtpContext($masterKey, $masterSalt);

$plainRtp = new RtpPacket(96, 501, 99000, 0xABCDEF, "CONFIDENTIAL_WEBRTC_AUDIO_STREAM_DATA");
$encryptedBuffer = $srtp->encryptRtp($plainRtp);

assertTest($encryptedBuffer !== $plainRtp->toBinary(), "SRTP encrypted buffer differs from plain RTP packet");
assertTest(strlen($encryptedBuffer) === strlen($plainRtp->toBinary()) + 10, "SRTP generates 10-byte HMAC-SHA1-80 authentication tag");

$decryptedRtp = $srtp->decryptRtp($encryptedBuffer);
assertTest($decryptedRtp->getPayload() === $plainRtp->getPayload(), "SRTP decrypts payload back to original plaintext");

// -----------------------------------------------------------------------------
// 5. MediaTrack & PeerConnection (SFU Topology)
// -----------------------------------------------------------------------------
$simulcastMap = [
    SimulcastLayer::HIGH->value => 10001,
    SimulcastLayer::MEDIUM->value => 10002,
    SimulcastLayer::LOW->value => 10003,
];
$videoTrack = new MediaTrack(
    id: 'track-video-1',
    kind: MediaType::VIDEO,
    ssrc: 10001,
    payloadType: 96,
    codec: 'VP8',
    simulcastSsrcs: $simulcastMap
);

assertTest($videoTrack->isSimulcast() === true, "MediaTrack recognized as Simulcast");
assertTest($videoTrack->getSsrcForLayer(SimulcastLayer::LOW) === 10003, "MediaTrack maps LOW layer to SSRC 10003");

$alice = new PeerConnection('peer-alice', 'Alice');
$alice->publishTrack($videoTrack);
$alice->setAudioLevel(-25); // Speaking loudly

$bob = new PeerConnection('peer-bob', 'Bob');
$bob->subscribe('track-video-1', SimulcastLayer::LOW); // Bob on slow connection
$bob->setAudioLevel(-110); // Silent

$charlie = new PeerConnection('peer-charlie', 'Charlie');
$charlie->subscribe('track-video-1', SimulcastLayer::HIGH); // Charlie on gigabit fiber
$charlie->setAudioLevel(-45); // Speaking softly

assertTest($alice->getAudioLevel() === -25, "Alice audio level set to -25 dBov");
assertTest($bob->getSubscribedLayer('track-video-1') === SimulcastLayer::LOW, "Bob subscribed to LOW layer");
assertTest($charlie->getSubscribedLayer('track-video-1') === SimulcastLayer::HIGH, "Charlie subscribed to HIGH layer");

// -----------------------------------------------------------------------------
// 6. SfuRoom & SimulcastRouter Multi-Party Fan-Out
// -----------------------------------------------------------------------------
$room = new SfuRoom('room-alpha', 'Engineering Standup', maxPeers: 10);
$room->addPeer($alice);
$room->addPeer($bob);
$room->addPeer($charlie);

assertTest(count($room->getPeers()) === 3, "SfuRoom has 3 peers joined");

// Active Speaker Detection
$activeSpeaker = $room->getActiveSpeaker();
assertTest($activeSpeaker !== null && $activeSpeaker->getId() === 'peer-alice', "Alice correctly detected as highest energy active speaker");

// Audio ranking
$ranked = $room->getRankedSpeakers(2);
assertTest($ranked[0]['peerId'] === 'peer-alice' && $ranked[1]['peerId'] === 'peer-charlie', "Speakers correctly ranked by VAD energy");

// Selective Forwarding Fan-out
$bobReceived = [];
$charlieReceived = [];
$bob->setPacketSink(function (RtpPacket $p, string $trackId) use (&$bobReceived) {
    $bobReceived[] = $p;
});
$charlie->setPacketSink(function (RtpPacket $p, string $trackId) use (&$charlieReceived) {
    $charlieReceived[] = $p;
});

// Alice transmits a HIGH layer packet (SSRC 10001)
$highPacket = new RtpPacket(96, 50, 1000, 10001, 'HIGH_RES_FRAME');
$forwardedCount1 = $room->broadcastPacket('peer-alice', 'track-video-1', $highPacket);

assertTest($forwardedCount1 === 1, "Only 1 subscriber received HIGH layer packet (Charlie)");
assertTest(count($charlieReceived) === 1, "Charlie received HIGH layer packet");
assertTest(count($bobReceived) === 0, "Bob did not receive HIGH layer packet");

// Alice transmits a LOW layer packet (SSRC 10003)
$lowPacket = new RtpPacket(96, 20, 1000, 10003, 'LOW_RES_FRAME');
$forwardedCount2 = $room->broadcastPacket('peer-alice', 'track-video-1', $lowPacket);

assertTest($forwardedCount2 === 1, "Only 1 subscriber received LOW layer packet (Bob)");
assertTest(count($bobReceived) === 1, "Bob received LOW layer packet");

// Keyframe PLI Request callback
$pliReceived = false;
$room->onKeyframeRequest(function ($pubId, $trId, $pli) use (&$pliReceived) {
    $pliReceived = true;
});
$room->requestKeyframe('peer-alice', 'track-video-1');
assertTest($pliReceived === true, "Keyframe request successfully triggers PLI callback to publisher");

// -----------------------------------------------------------------------------
// 7. NalUnit (H.264 Slice Parsing & Annex B)
// -----------------------------------------------------------------------------
// Construct an Annex B stream: SPS (type 7), PPS (type 8), IDR Keyframe (type 5), Non-IDR Slice (type 1)
$spsNal = "\x67\x42\x00\x1f"; // SPS
$ppsNal = "\x68\xce\x3c\x80"; // PPS
$idrNal = "\x65\x88\x84\x00\x10\x20"; // IDR slice
$nonIdrNal = "\x41\x9a\x01\x02"; // Non-IDR P-slice

$annexBStream = "\x00\x00\x00\x01" . $spsNal
              . "\x00\x00\x00\x01" . $ppsNal
              . "\x00\x00\x00\x01" . $idrNal
              . "\x00\x00\x00\x01" . $nonIdrNal;

$parsedUnits = NalUnit::parseAnnexB($annexBStream);
assertTest(count($parsedUnits) === 4, "Parsed exactly 4 NAL units from Annex B byte stream");
assertTest($parsedUnits[0]->getType() === NalUnit::TYPE_SPS, "First NAL unit is SPS (type 7)");
assertTest($parsedUnits[1]->getType() === NalUnit::TYPE_PPS, "Second NAL unit is PPS (type 8)");
assertTest($parsedUnits[2]->isKeyframe() === true, "Third NAL unit is IDR Keyframe (type 5)");
assertTest($parsedUnits[3]->getType() === NalUnit::TYPE_NON_IDR, "Fourth NAL unit is Non-IDR slice (type 1)");

// -----------------------------------------------------------------------------
// 8. HlsSegmenter (RFC 8216 Playlists)
// -----------------------------------------------------------------------------
$hls = HlsSegmenter::createStandardProfiles('https://cdn.netfusion.io/live/');
$master = $hls->generateMasterPlaylist();

assertTest(str_starts_with($master, '#EXTM3U'), "HLS Master Playlist starts with #EXTM3U header");
assertTest(str_contains($master, '#EXT-X-STREAM-INF:BANDWIDTH=4500000,RESOLUTION=1920x1080'), "Master playlist includes 1080p profile");
assertTest(str_contains($master, '#EXT-X-STREAM-INF:BANDWIDTH=400000,RESOLUTION=640x360'), "Master playlist includes 360p profile");

// Media playlist
$mediaHls = new HlsSegmenter(targetDuration: 4.0);
$mediaHls->addSegment(3.99, 'seg001.ts');
$mediaHls->addSegment(4.00, 'seg002.ts');
$mediaHls->addSegment(3.85, 'seg003.ts');
$mediaPlaylist = $mediaHls->generateMediaPlaylist(isVod: true);

assertTest(str_contains($mediaPlaylist, '#EXT-X-TARGETDURATION:4'), "Media playlist specifies target duration 4");
assertTest(str_contains($mediaPlaylist, '#EXTINF:3.990000,'), "Media playlist includes segment durations");
assertTest(str_ends_with(trim($mediaPlaylist), '#EXT-X-ENDLIST'), "VOD media playlist ends with #EXT-X-ENDLIST");

// -----------------------------------------------------------------------------
// 9. CongestionController (GCC Bandwidth Adaptation)
// -----------------------------------------------------------------------------
$cc = new CongestionController(initialBandwidthKbps: 2000);
assertTest($cc->getEstimatedBandwidthKbps() === 2000, "Initial bandwidth is 2000 kbps");
assertTest($cc->getRecommendedLayer() === SimulcastLayer::HIGH, "Recommended layer for 2000 kbps is HIGH");

// High packet loss (25%) -> Back off
$reducedBw = $cc->updateLossFeedback(0.25, rttMs: 80);
assertTest($reducedBw < 2000, "High loss reduces estimated bandwidth");
assertTest($cc->getState() === 'DECREASING', "Controller state transitioned to DECREASING");

// Extreme loss -> Drops to LOW layer
while ($cc->getEstimatedBandwidthKbps() >= 500) {
    $cc->updateLossFeedback(0.70, rttMs: 150);
}
assertTest($cc->getRecommendedLayer() === SimulcastLayer::LOW, "Severe congestion switches recommended layer to LOW");

// Clean network (0% loss) -> Additive/multiplicative recovery
$recoveredBw = $cc->updateLossFeedback(0.0, rttMs: 25);
assertTest($cc->getState() === 'INCREASING', "Clean network transitions controller to INCREASING");

// -----------------------------------------------------------------------------
// 10. MediaServerManager Facade & Global Stats
// -----------------------------------------------------------------------------
$manager = new MediaServerManager([
    'default_video_codec' => 'H264',
    'max_room_peers' => 50,
]);
$room1 = $manager->createRoom('conf-101', 'General Meeting');
$room2 = $manager->createRoom('conf-102', 'Private 1on1');

assertTest($manager->hasRoom('conf-101') === true, "MediaServerManager recognizes created room");
assertTest($manager->getRoom('conf-101')->getName() === 'General Meeting', "Room metadata matches");

$stats = $manager->getGlobalStats();
assertTest($stats['totalRooms'] === 2, "Global stats reflect 2 active rooms");
assertTest($stats['defaultVideoCodec'] === 'H264', "Global stats reflect configured default video codec");

$deleted = $manager->deleteRoom('conf-102');
assertTest($deleted === true && !$manager->hasRoom('conf-102'), "Room successfully deleted from media server");

// -----------------------------------------------------------------------------
// 11. Database Migration (media_rooms, media_peers, media_recordings)
// -----------------------------------------------------------------------------
$dbConn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$pdo = $dbConn->getPdo();

$migration = require __DIR__ . '/../database/migrations/2026_09_28_000071_create_media_server_tables.php';
$migration->up($dbConn);

// Verify tables exist
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('media_rooms', 'media_peers', 'media_recordings')");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($tables) === 3, "Migration successfully created media_rooms, media_peers, media_recordings tables");

// Insert and verify a room record
$pdo->exec("INSERT INTO media_rooms (id, name, max_peers) VALUES ('room-db-1', 'DB Conference', 50)");
$roomRow = $pdo->query("SELECT * FROM media_rooms WHERE id = 'room-db-1'")->fetch(PDO::FETCH_ASSOC);
assertTest($roomRow['name'] === 'DB Conference', "Room inserted and retrieved from media_rooms table");

// Rollback
$migration->down($dbConn);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('media_rooms', 'media_peers', 'media_recordings')");
$remainingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($remainingTables) === 0, "Migration down successfully drops all media server tables");

echo "\n========================================================\n";
echo " Phase 88 Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
