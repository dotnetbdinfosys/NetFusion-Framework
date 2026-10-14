<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 41 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase41.php
| Module    : Sockets
| Purpose   : Comprehensive tests for RFC 6455 WebSocket Handshake,
|             Binary Frame Parser/Encoder, ChannelManager, Presence Rooms,
|             WebSocketServer Loop, and Broadcasting Bridge.
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

namespace NetFusion\Tests\Sockets;

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Broadcasting\BroadcastManager;
use NetFusion\Core\Broadcasting\Drivers\SocketBroadcastDriver;
use NetFusion\Core\Sockets\ChannelManager;
use NetFusion\Core\Sockets\Connection;
use NetFusion\Core\Sockets\Contracts\ConnectionInterface;
use NetFusion\Core\Sockets\Contracts\FrameInterface;
use NetFusion\Core\Sockets\Frame;
use NetFusion\Core\Sockets\Handshake;
use NetFusion\Core\Sockets\SocketManager;
use NetFusion\Core\Sockets\WebSocketServer;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 41 — Real-Time WebSockets & Socket Engine Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: RFC 6455 HTTP Handshake Validator & Key Computation
// =============================================================================
echo "\033[36m[Section 1: RFC 6455 HTTP Handshake Validator & Key Computation]\033[0m\n";

// RFC 6455 standard example key
$sampleKey = 'dGhlIHNhbXBsZSBub25jZQ==';
$expectedAccept = 's3pPLMBiTxaQ9kYGzzhZRbK+xOo=';
$computedAccept = Handshake::computeAcceptKey($sampleKey);
t($computedAccept === $expectedAccept, 'Handshake::computeAcceptKey matches RFC 6455 standard test vector');

$validHttp = "GET /chat?room=general&token=secret123 HTTP/1.1\r\n" .
    "Host: localhost:8080\r\n" .
    "Upgrade: websocket\r\n" .
    "Connection: Upgrade\r\n" .
    "Sec-WebSocket-Key: {$sampleKey}\r\n" .
    "Sec-WebSocket-Version: 13\r\n" .
    "Origin: http://localhost:3000\r\n\r\n";

$hs = new Handshake($validHttp);
t($hs->isValid() === true, 'Handshake validates standard RFC 6455 HTTP upgrade request');
t($hs->getPath() === '/chat', 'Handshake extracts request path /chat');
t($hs->getQueryParams() === ['room' => 'general', 'token' => 'secret123'], 'Handshake parses URI query parameters');
t($hs->getHeader('host') === 'localhost:8080', 'Handshake retrieves normalized header Host');

$response = $hs->getResponse();
t(str_contains($response, 'HTTP/1.1 101 Switching Protocols'), 'Handshake response contains HTTP 101 Switching Protocols');
t(str_contains($response, "Sec-WebSocket-Accept: {$expectedAccept}"), 'Handshake response contains computed Sec-WebSocket-Accept header');

// Invalid handshake (POST method)
$invalidHttp = "POST /chat HTTP/1.1\r\nHost: localhost:8080\r\n\r\n";
$badHs = new Handshake($invalidHttp);
t($badHs->isValid() === false, 'Handshake rejects non-GET HTTP methods');
t(str_contains($badHs->getResponse(), 'HTTP/1.1 400 Bad Request'), 'Invalid handshake returns HTTP 400 Bad Request');

// Missing Sec-WebSocket-Key
$noKeyHttp = "GET /chat HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\n\r\n";
$noKeyHs = new Handshake($noKeyHttp);
t($noKeyHs->isValid() === false, 'Handshake rejects requests missing Sec-WebSocket-Key');

// =============================================================================
// Section 2: RFC 6455 Binary Frame Encoder & Decoder
// =============================================================================
echo "\033[36m[Section 2: RFC 6455 Binary Frame Encoder & Decoder]\033[0m\n";

// 1. Small Text Frame (< 126 bytes)
$smallText = 'Hello NetFusion WebSocket!';
$encodedSmallUnmasked = Frame::encodeData($smallText, FrameInterface::OPCODE_TEXT, masked: false);
$consumed1 = 0;
$decoded1 = Frame::decode($encodedSmallUnmasked, $consumed1);
t($decoded1 !== null && $decoded1->getPayload() === $smallText, 'Small unmasked text frame encodes and decodes correctly');
t($decoded1->getOpcode() === FrameInterface::OPCODE_TEXT, 'Decoded opcode is OPCODE_TEXT');
t($consumed1 === strlen($encodedSmallUnmasked), 'Consumed byte count matches frame length');

// 2. Small Masked Text Frame (Client to Server simulation)
$encodedSmallMasked = Frame::encodeData($smallText, FrameInterface::OPCODE_TEXT, masked: true);
$consumed2 = 0;
$decoded2 = Frame::decode($encodedSmallMasked, $consumed2);
t($decoded2 !== null && $decoded2->getPayload() === $smallText, 'Small masked text frame unmasks payload correctly with XOR key');
t($decoded2->isMasked() === true, 'Masked flag is true');

// 3. Medium Frame (126 - 65535 bytes: 2-byte extended length)
$mediumPayload = str_repeat('ABCDEF1234', 200); // 2000 bytes
$encodedMedium = Frame::encodeData($mediumPayload, FrameInterface::OPCODE_TEXT, masked: true);
$consumed3 = 0;
$decoded3 = Frame::decode($encodedMedium, $consumed3);
t($decoded3 !== null && $decoded3->getPayload() === $mediumPayload, 'Medium frame (2000 bytes) encodes with 16-bit extended length header and decodes correctly');
t(strlen($decoded3->getPayload()) === 2000, 'Medium payload length is exact 2000 bytes');

// 4. Large Frame (> 65535 bytes: 8-byte 64-bit extended length)
$largePayload = str_repeat('X', 70000); // 70 KB
$encodedLarge = Frame::encodeData($largePayload, FrameInterface::OPCODE_BINARY, masked: false);
$consumed4 = 0;
$decoded4 = Frame::decode($encodedLarge, $consumed4);
t($decoded4 !== null && strlen($decoded4->getPayload()) === 70000, 'Large frame (70 KB) encodes with 64-bit extended length header and decodes');
t($decoded4->getOpcode() === FrameInterface::OPCODE_BINARY, 'Large frame opcode is OPCODE_BINARY');

// 5. Ping & Pong Frames
$pingFrame = Frame::ping('heartbeat-data', masked: false);
t($pingFrame->getOpcode() === FrameInterface::OPCODE_PING, 'Frame::ping() creates OPCODE_PING frame');
$pongEncoded = Frame::encodeData('heartbeat-data', FrameInterface::OPCODE_PONG, masked: false);
$decodedPong = Frame::decode($pongEncoded);
t($decodedPong !== null && $decodedPong->getOpcode() === FrameInterface::OPCODE_PONG && $decodedPong->getPayload() === 'heartbeat-data', 'Pong frame decodes correctly');

// 6. Close Frame with Code & Reason
$closeFrame = Frame::close(1000, 'Normal Closure', masked: false);
$decodedClose = Frame::decode($closeFrame->encode());
t($decodedClose !== null && $decodedClose->getOpcode() === FrameInterface::OPCODE_CLOSE, 'Close frame decodes opcode OPCODE_CLOSE');
$closeStatus = $decodedClose->getCloseStatus();
t($closeStatus['code'] === 1000 && $closeStatus['reason'] === 'Normal Closure', 'Close frame extracts code 1000 and reason');

// 7. Incomplete Frame buffer handling
$partialBuffer = substr($encodedSmallUnmasked, 0, 5); // truncated
$consumedPartial = 0;
$decodedPartial = Frame::decode($partialBuffer, $consumedPartial);
t($decodedPartial === null && $consumedPartial === 0, 'Frame::decode returns null and 0 consumed bytes on incomplete TCP stream buffer');

// =============================================================================
// Section 3: Connection & Client State Management
// =============================================================================
echo "\033[36m[Section 3: Connection & Client State Management]\033[0m\n";

$mockSocket = fopen('php://temp', 'r+');
$conn = new Connection($mockSocket, 'conn-test-001');

t($conn->getId() === 'conn-test-001', 'Connection returns assigned unique ID');
t($conn->isHandshakeCompleted() === false, 'Connection handshake is initially false');

$conn->setHandshakeCompleted(true);
$conn->setHandshakeData('/live', ['host' => 'example.com'], ['user_id' => '42']);
t($conn->isHandshakeCompleted() === true, 'Connection handshake marked true');
t($conn->getPath() === '/live', 'Connection stores handshake path');
t($conn->getQueryParams() === ['user_id' => '42'], 'Connection stores query parameters');

// Attributes
$conn->set('role', 'admin');
$conn->set('tenant_id', 'acme-corp');
t($conn->has('role') && $conn->get('role') === 'admin', 'Connection sets and gets custom attributes');
t($conn->get('tenant_id') === 'acme-corp', 'Connection preserves tenant_id attribute');

// Subscriptions
$conn->subscribe('orders');
$conn->subscribe('notifications');
t($conn->isSubscribed('orders') === true, 'Connection reports subscribed to orders');
t(count($conn->getChannels()) === 2, 'Connection lists 2 subscribed channels');
$conn->unsubscribe('orders');
t($conn->isSubscribed('orders') === false, 'Connection unregisters channel on unsubscribe');

// Sending text frame to mock socket
$conn->send('Hello Stream');
rewind($mockSocket);
$writtenData = stream_get_contents($mockSocket);
$decodedSent = Frame::decode($writtenData);
t($decodedSent !== null && $decodedSent->getPayload() === 'Hello Stream', 'Connection::send() serializes text frame to socket resource');
$conn->close();

// =============================================================================
// Section 4: ChannelManager, Pub/Sub & Presence Rooms
// =============================================================================
echo "\033[36m[Section 4: ChannelManager, Pub/Sub & Presence Rooms]\033[0m\n";

$channelMgr = new ChannelManager();

// Create 3 mock connections
$sock1 = fopen('php://temp', 'r+');
$sock2 = fopen('php://temp', 'r+');
$sock3 = fopen('php://temp', 'r+');

$c1 = new Connection($sock1, 'client-1');
$c2 = new Connection($sock2, 'client-2');
$c3 = new Connection($sock3, 'client-3');

// 1. Join public channel
$channelMgr->join('general-chat', $c1);
$channelMgr->join('general-chat', $c2);
$channelMgr->join('alerts', $c3);

t($channelMgr->getChannelCount('general-chat') === 2, 'ChannelManager tracks 2 members in general-chat');
t($channelMgr->getChannelCount('alerts') === 1, 'ChannelManager tracks 1 member in alerts');
t($channelMgr->isSubscribed('general-chat', $c1) === true, 'c1 is subscribed to general-chat');

// 2. Broadcast to channel
$delivered = $channelMgr->broadcast('general-chat', 'NewMessage', ['text' => 'Hi All']);
t($delivered === 2, 'ChannelManager::broadcast() delivered event to 2 channel subscribers');

// Verify c1 and c2 received payload
rewind($sock1);
$c1Content = stream_get_contents($sock1);
$c1Frame = Frame::decode($c1Content);
t($c1Frame !== null && str_contains($c1Frame->getPayload(), 'NewMessage'), 'Subscriber c1 received serialized JSON NewMessage frame');

// 3. Broadcast with exception (except sender)
$deliveredExcept = $channelMgr->broadcast('general-chat', 'UserTyping', ['user' => 'c1'], except: $c1);
t($deliveredExcept === 1, 'ChannelManager::broadcast() with except excludes the sender (c1)');

// 4. Presence Channel
$sockP1 = fopen('php://temp', 'r+');
$sockP2 = fopen('php://temp', 'r+');
$cP1 = new Connection($sockP1, 'presence-user-1');
$cP2 = new Connection($sockP2, 'presence-user-2');

$channelMgr->join('presence-dashboard', $cP1, ['name' => 'Ashish', 'role' => 'Lead']);
$channelMgr->join('presence-dashboard', $cP2, ['name' => 'John', 'role' => 'Developer']);

$presenceList = $channelMgr->getPresenceData('presence-dashboard');
t(count($presenceList) === 2, 'Presence channel tracks member metadata');
t(isset($presenceList['presence-user-1']) && $presenceList['presence-user-1']['name'] === 'Ashish', 'Presence member metadata stored');

$channelMgr->leave('presence-dashboard', $cP1);
t(count($channelMgr->getPresenceData('presence-dashboard')) === 1, 'Presence member removed upon leave');

// Cleanup
$channelMgr->leaveAll($c1);
$channelMgr->leaveAll($c2);
$channelMgr->leaveAll($c3);
$channelMgr->leaveAll($cP2);
t($channelMgr->getChannelCount('general-chat') === 0, 'Channels cleaned up after leaveAll');

// =============================================================================
// Section 5: WebSocketServer Loop & Protocol Actions
// =============================================================================
echo "\033[36m[Section 5: WebSocketServer Loop & Protocol Actions]\033[0m\n";

$wsServer = new WebSocketServer('127.0.0.1', 8990, 8991);
t($wsServer->getHost() === '127.0.0.1', 'WebSocketServer configured host');
t($wsServer->getPort() === 8990, 'WebSocketServer configured port');
t($wsServer->getPushPort() === 8991, 'WebSocketServer configured push port');
t($wsServer->getChannelManager() instanceof ChannelManager, 'WebSocketServer provides ChannelManager');

// Test protocol action handling via mock message
$mockClientSock = fopen('php://temp', 'r+');
$testConn = new Connection($mockClientSock, 'proto-conn-1');
$wsServer->onOpen($testConn);

// Simulate incoming client subscribing via protocol JSON
$subscribeActionMsg = json_encode([
    'action'  => 'subscribe',
    'channel' => 'crypto-prices',
]);
$wsServer->onMessage($testConn, $subscribeActionMsg);

t($testConn->isSubscribed('crypto-prices') === true, 'WebSocketServer handles {"action":"subscribe"} protocol message');

// =============================================================================
// Section 6: Broadcasting Integration & Global Helpers
// =============================================================================
echo "\033[36m[Section 6: Broadcasting Integration & Global Helpers]\033[0m\n";

/** @var BroadcastManager $broadcast */
$broadcast = $app->make(BroadcastManager::class);
$socketDriver = $broadcast->driver('socket');
t($socketDriver instanceof SocketBroadcastDriver, 'BroadcastManager resolves socket driver as SocketBroadcastDriver');

$wsDriver = $broadcast->driver('websocket');
t($wsDriver instanceof SocketBroadcastDriver, 'BroadcastManager resolves websocket driver as SocketBroadcastDriver');

// Global helpers
t(function_exists('socket'), 'Global helper socket() exists');
t(function_exists('websocket'), 'Global helper websocket() exists');
t(socket() instanceof SocketManager, 'socket() helper returns SocketManager');
t(websocket() instanceof SocketManager, 'websocket() helper returns SocketManager');

echo "\n=================================================================\n";
echo "  Results: {$passed}/{$total} Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "=================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
