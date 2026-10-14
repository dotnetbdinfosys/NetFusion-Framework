<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/WebSocketServer.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets
| Purpose   : High-performance non-blocking WebSocket & IPC Push server engine.
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

namespace NetFusion\Core\Sockets;

use Closure;
use NetFusion\Core\Sockets\Contracts\ConnectionInterface;
use NetFusion\Core\Sockets\Contracts\FrameInterface;
use NetFusion\Core\Sockets\Contracts\WebSocketHandlerInterface;
use RuntimeException;
use Throwable;

/**
 * Class WebSocketServer
 *
 * Implements RFC 6455 pure PHP WebSocket server using non-blocking stream_select loop.
 *
 * @package NetFusion\Core\Sockets
 */
class WebSocketServer implements WebSocketHandlerInterface
{
    /**
     * Master listening socket.
     *
     * @var resource|null
     */
    protected mixed $masterSocket = null;

    /**
     * Push IPC listening socket (for background workers & HTTP requests to broadcast).
     *
     * @var resource|null
     */
    protected mixed $pushSocket = null;

    /**
     * Map of raw socket resource ID to Connection instance.
     *
     * @var array<int, Connection>
     */
    protected array $connections = [];

    /**
     * Map of raw socket resource ID to stream resource.
     *
     * @var array<int, resource>
     */
    protected array $sockets = [];

    /**
     * Channel manager for Pub/Sub and Presence rooms.
     */
    protected ChannelManager $channelManager;

    /**
     * Custom user handler.
     */
    protected ?WebSocketHandlerInterface $customHandler = null;

    /**
     * Server running state.
     */
    protected bool $running = false;

    /**
     * Server settings.
     */
    protected string $host = '0.0.0.0';
    protected int $port = 8080;
    protected int $pushPort = 8081;
    protected int $maxClients = 1000;
    protected int $pingInterval = 30;
    protected int $idleTimeout = 120;
    protected int $lastHeartbeatCheck = 0;

    /**
     * Optional message interceptor / event listener callbacks.
     *
     * @var array<string, array<callable>>
     */
    protected array $eventListeners = [];

    public function __construct(
        string $host = '0.0.0.0',
        int $port = 8080,
        int $pushPort = 8081,
        ?ChannelManager $channelManager = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->pushPort = $pushPort;
        $this->channelManager = $channelManager ?? new ChannelManager();
    }

    /**
     * Get ChannelManager.
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Set custom handler.
     */
    public function setHandler(WebSocketHandlerInterface $handler): self
    {
        $this->customHandler = $handler;
        return $this;
    }

    /**
     * Bind master WebSocket server and IPC push port.
     */
    public function bind(): self
    {
        $context = stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'backlog'      => 1024,
            ],
        ]);

        $wsUri = "tcp://{$this->host}:{$this->port}";
        $this->masterSocket = @stream_socket_server(
            $wsUri,
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$this->masterSocket) {
            throw new RuntimeException("Failed to bind WebSocket server on {$wsUri}: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->masterSocket, false);
        $masterId = (int) $this->masterSocket;
        $this->sockets[$masterId] = $this->masterSocket;

        // Bind IPC Push Socket
        if ($this->pushPort > 0) {
            $pushUri = "tcp://127.0.0.1:{$this->pushPort}";
            $this->pushSocket = @stream_socket_server(
                $pushUri,
                $errno2,
                $errstr2,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
            );

            if ($this->pushSocket) {
                stream_set_blocking($this->pushSocket, false);
                $pushId = (int) $this->pushSocket;
                $this->sockets[$pushId] = $this->pushSocket;
            }
        }

        $this->lastHeartbeatCheck = time();
        return $this;
    }

    /**
     * Start the server loop.
     *
     * @param callable|null $onStart Optional callback when loop starts.
     */
    public function listen(?callable $onStart = null): void
    {
        if (!$this->masterSocket) {
            $this->bind();
        }

        $this->running = true;

        if ($onStart !== null) {
            $onStart($this);
        }

        while ($this->running) {
            $this->tick();
        }

        $this->shutdown();
    }

    /**
     * Execute a single tick of the event loop (non-blocking / short select).
     *
     * @param int $timeoutMicroseconds
     */
    public function tick(int $timeoutMicroseconds = 200000): void
    {
        $read = $this->sockets;
        $write = null;
        $except = null;

        if (empty($read)) {
            return;
        }

        $sec = (int) ($timeoutMicroseconds / 1000000);
        $usec = (int) ($timeoutMicroseconds % 1000000);

        $numChanged = @stream_select($read, $write, $except, $sec, $usec);
        if ($numChanged === false || $numChanged === 0) {
            $this->checkHeartbeats();
            return;
        }

        foreach ($read as $sock) {
            $sockId = (int) $sock;

            if ($sock === $this->masterSocket) {
                $this->acceptWebSocketClient();
            } elseif ($sock === $this->pushSocket) {
                $this->acceptPushClient();
            } else {
                $this->readClientSocket($sock, $sockId);
            }
        }

        $this->checkHeartbeats();
    }

    /**
     * Accept incoming WebSocket client connection.
     */
    protected function acceptWebSocketClient(): void
    {
        $client = @stream_socket_accept($this->masterSocket, 0);
        if (!$client) {
            return;
        }

        if (count($this->connections) >= $this->maxClients) {
            @fclose($client);
            return;
        }

        stream_set_blocking($client, false);
        $id = (int) $client;
        $conn = new Connection($client);

        $this->sockets[$id] = $client;
        $this->connections[$id] = $conn;
    }

    /**
     * Accept and process local push socket payload (IPC Broadcast).
     */
    protected function acceptPushClient(): void
    {
        $client = @stream_socket_accept($this->pushSocket, 0);
        if (!$client) {
            return;
        }

        stream_set_blocking($client, false);
        $data = @fread($client, 65536);
        if ($data !== false && !empty(trim($data))) {
            $payload = json_decode(trim($data), true);
            if (is_array($payload)) {
                $channel = $payload['channel'] ?? '';
                $event = $payload['event'] ?? 'message';
                $messageData = $payload['data'] ?? [];

                if (!empty($channel)) {
                    $this->channelManager->broadcast($channel, $event, $messageData);
                } else {
                    $this->channelManager->broadcastAll($event, $messageData);
                }

                @fwrite($client, json_encode(['status' => 'ok', 'delivered' => true]) . "\n");
            }
        }

        @fclose($client);
    }

    /**
     * Read data from connected client socket.
     */
    protected function readClientSocket(mixed $sock, int $sockId): void
    {
        if (!isset($this->connections[$sockId])) {
            return;
        }

        $conn = $this->connections[$sockId];
        $buffer = @fread($sock, 8192);

        if ($buffer === false || strlen($buffer) === 0) {
            // Client closed stream
            $this->disconnectClient($sockId, 1001, 'Stream closed by peer');
            return;
        }

        $conn->appendReadBuffer($buffer);

        if (!$conn->isHandshakeCompleted()) {
            $this->handleHandshake($conn, $sockId);
            return;
        }

        $this->processFrames($conn, $sockId);
    }

    /**
     * Process HTTP handshake for incoming connection.
     */
    protected function handleHandshake(Connection $conn, int $sockId): void
    {
        $rawBuffer = $conn->getReadBuffer();
        if (!str_contains($rawBuffer, "\r\n\r\n")) {
            // Wait for complete HTTP headers
            return;
        }

        $handshake = new Handshake($rawBuffer);
        if (!$handshake->isValid()) {
            $conn->writeRaw($handshake->getResponse());
            $this->disconnectClient($sockId, 1002, $handshake->getErrorMessage());
            return;
        }

        $response = $handshake->getResponse();
        $conn->writeRaw($response);
        $conn->setHandshakeCompleted(true);
        $conn->setHandshakeData($handshake->getPath(), $handshake->getHeaders(), $handshake->getQueryParams());
        $conn->clearReadBuffer();

        $this->onOpen($conn);
    }

    /**
     * Process WebSocket binary frames from client read buffer.
     */
    protected function processFrames(Connection $conn, int $sockId): void
    {
        while (true) {
            $buffer = $conn->getReadBuffer();
            if (empty($buffer)) {
                break;
            }

            $consumedBytes = 0;
            $frame = Frame::decode($buffer, $consumedBytes);
            if ($frame === null || $consumedBytes === 0) {
                // Incomplete frame, await more TCP packets
                break;
            }

            $conn->consumeReadBuffer($consumedBytes);
            $opcode = $frame->getOpcode();

            switch ($opcode) {
                case FrameInterface::OPCODE_TEXT:
                    $this->onMessage($conn, $frame->getPayload());
                    break;

                case FrameInterface::OPCODE_BINARY:
                    $this->onBinary($conn, $frame->getPayload());
                    break;

                case FrameInterface::OPCODE_PING:
                    $conn->pong($frame->getPayload());
                    break;

                case FrameInterface::OPCODE_PONG:
                    $conn->touch();
                    break;

                case FrameInterface::OPCODE_CLOSE:
                    $status = $frame->getCloseStatus();
                    $this->disconnectClient($sockId, $status['code'], $status['reason']);
                    return;

                default:
                    // Ignore unknown opcode
                    break;
            }
        }
    }

    /**
     * Disconnect client and clean up.
     */
    public function disconnectClient(int $sockId, int $code = 1000, string $reason = ''): void
    {
        if (isset($this->connections[$sockId])) {
            $conn = $this->connections[$sockId];
            $this->channelManager->leaveAll($conn);

            try {
                $this->onClose($conn, $code, $reason);
            } catch (Throwable $e) {
                // Ignore errors during close hook
            }

            $conn->close($code, $reason);
            unset($this->connections[$sockId]);
        }

        if (isset($this->sockets[$sockId])) {
            if (is_resource($this->sockets[$sockId])) {
                @fclose($this->sockets[$sockId]);
            }
            unset($this->sockets[$sockId]);
        }
    }

    /**
     * Periodic heartbeat & idle ping check.
     */
    protected function checkHeartbeats(): void
    {
        $now = time();
        if ($now - $this->lastHeartbeatCheck < 10) {
            return;
        }

        $this->lastHeartbeatCheck = $now;

        foreach ($this->connections as $sockId => $conn) {
            $idleTime = $now - $conn->getLastActiveAt();

            if ($idleTime > $this->idleTimeout) {
                $this->disconnectClient($sockId, 1000, 'Connection idle timeout');
                continue;
            }

            if ($idleTime > $this->pingInterval) {
                $conn->ping();
            }
        }
    }

    /**
     * Stop the server.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Shutdown server and close all sockets.
     */
    public function shutdown(): void
    {
        $this->running = false;

        foreach (array_keys($this->connections) as $sockId) {
            $this->disconnectClient($sockId, 1001, 'Server shutting down');
        }

        if ($this->masterSocket && is_resource($this->masterSocket)) {
            @fclose($this->masterSocket);
            $this->masterSocket = null;
        }

        if ($this->pushSocket && is_resource($this->pushSocket)) {
            @fclose($this->pushSocket);
            $this->pushSocket = null;
        }

        $this->sockets = [];
        $this->connections = [];
    }

    // =========================================================================
    // WebSocketHandlerInterface Lifecycle Implementation
    // =========================================================================

    public function onOpen(ConnectionInterface $conn): void
    {
        if ($this->customHandler !== null) {
            $this->customHandler->onOpen($conn);
        }
        $this->triggerEvent('open', $conn);
    }

    public function onMessage(ConnectionInterface $conn, string $message): void
    {
        // Check for NetFusion JSON protocol actions
        $data = json_decode($message, true);
        if (is_array($data) && isset($data['action'])) {
            $this->handleProtocolAction($conn, $data);
        }

        if ($this->customHandler !== null) {
            $this->customHandler->onMessage($conn, $message);
        }
        $this->triggerEvent('message', $conn, $message);
    }

    /**
     * Handle NetFusion client protocol message (subscribe, unsubscribe, ping, whisper).
     *
     * @param ConnectionInterface  $conn
     * @param array<string, mixed> $data
     */
    protected function handleProtocolAction(ConnectionInterface $conn, array $data): void
    {
        $action = $data['action'] ?? '';
        $channel = (string) ($data['channel'] ?? '');

        switch ($action) {
            case 'subscribe':
                if (!empty($channel)) {
                    $memberData = $data['data'] ?? [];
                    $this->channelManager->join($channel, $conn, is_array($memberData) ? $memberData : []);
                    $conn->send((string) json_encode([
                        'event'   => 'subscription_succeeded',
                        'channel' => $channel,
                    ]));
                }
                break;

            case 'unsubscribe':
                if (!empty($channel)) {
                    $this->channelManager->leave($channel, $conn);
                }
                break;

            case 'ping':
                $conn->send((string) json_encode(['event' => 'pong', 'time' => time()]));
                break;

            case 'whisper':
                // Client-to-client whispering on subscribed channel
                if (!empty($channel) && $conn->isSubscribed($channel)) {
                    $event = $data['event'] ?? 'whisper';
                    $payload = $data['data'] ?? [];
                    $this->channelManager->broadcast($channel, (string) $event, is_array($payload) ? $payload : [], except: $conn);
                }
                break;
        }
    }

    public function onBinary(ConnectionInterface $conn, string $binaryData): void
    {
        if ($this->customHandler !== null) {
            $this->customHandler->onBinary($conn, $binaryData);
        }
        $this->triggerEvent('binary', $conn, $binaryData);
    }

    public function onClose(ConnectionInterface $conn, int $code, string $reason): void
    {
        if ($this->customHandler !== null) {
            $this->customHandler->onClose($conn, $code, $reason);
        }
        $this->triggerEvent('close', $conn, $code, $reason);
    }

    public function onError(ConnectionInterface $conn, Throwable $error): void
    {
        if ($this->customHandler !== null) {
            $this->customHandler->onError($conn, $error);
        }
        $this->triggerEvent('error', $conn, $error);
    }

    /**
     * Register an event listener closure.
     */
    public function on(string $event, callable $listener): self
    {
        $this->eventListeners[$event][] = $listener;
        return $this;
    }

    /**
     * Trigger registered event callbacks.
     */
    protected function triggerEvent(string $event, ...$args): void
    {
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $cb) {
                try {
                    $cb(...$args);
                } catch (Throwable $e) {
                    // Ignore listener errors
                }
            }
        }
    }

    /**
     * Get list of currently connected Connection instances.
     *
     * @return array<int, Connection>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Get count of active connections.
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Get host.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get push port.
     */
    public function getPushPort(): int
    {
        return $this->pushPort;
    }
}
