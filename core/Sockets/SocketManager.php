<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/SocketManager.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets
| Purpose   : High-level WebSocket & Real-Time Socket manager facade.
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

use NetFusion\Core\Application;
use RuntimeException;
use Throwable;

/**
 * Class SocketManager
 *
 * Facade and coordinator for creating servers, pushing events, and broadcasting to WebSocket clients.
 *
 * @package NetFusion\Core\Sockets
 */
class SocketManager
{
    /**
     * Cached server instance.
     */
    protected ?WebSocketServer $server = null;

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get or instantiate the WebSocket server instance.
     */
    public function server(): WebSocketServer
    {
        if ($this->server === null) {
            $host = (string) config('socket.host', '0.0.0.0');
            $port = (int) config('socket.port', 8080);
            $pushPort = (int) config('socket.push_port', 8081);

            $this->server = new WebSocketServer(
                host: $host,
                port: $port,
                pushPort: $pushPort
            );
        }

        return $this->server;
    }

    /**
     * Create a new custom WebSocket server instance.
     */
    public function createServer(string $host = '0.0.0.0', int $port = 8080, int $pushPort = 8081): WebSocketServer
    {
        return new WebSocketServer($host, $port, $pushPort);
    }

    /**
     * Push a broadcast event to connected WebSocket clients via local IPC Push socket.
     *
     * @param string|array<string> $channels
     * @param string               $event
     * @param array<string, mixed> $data
     * @return bool
     */
    public function push(string|array $channels, string $event, array $data = []): bool
    {
        $channelList = is_array($channels) ? $channels : [$channels];
        $pushPort = (int) config('socket.push_port', 8081);
        $pushHost = (string) config('socket.push_host', '127.0.0.1');

        $success = true;

        foreach ($channelList as $channel) {
            $payload = json_encode([
                'action'  => 'broadcast',
                'channel' => (string) $channel,
                'event'   => $event,
                'data'    => $data,
                'time'    => microtime(true),
            ]);

            $sent = $this->sendIpcPayload($pushHost, $pushPort, (string) $payload);
            if (!$sent) {
                // If IPC push socket is unavailable, fallback to in-process server channel manager if active
                if ($this->server !== null) {
                    $this->server->getChannelManager()->broadcast((string) $channel, $event, $data);
                } else {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Push message to all clients on all channels.
     *
     * @param string               $event
     * @param array<string, mixed> $data
     */
    public function pushAll(string $event, array $data = []): bool
    {
        $pushPort = (int) config('socket.push_port', 8081);
        $pushHost = (string) config('socket.push_host', '127.0.0.1');

        $payload = json_encode([
            'action'  => 'broadcast_all',
            'channel' => '',
            'event'   => $event,
            'data'    => $data,
            'time'    => microtime(true),
        ]);

        $sent = $this->sendIpcPayload($pushHost, $pushPort, (string) $payload);
        if (!$sent && $this->server !== null) {
            $this->server->getChannelManager()->broadcastAll($event, $data);
            return true;
        }

        return $sent;
    }

    /**
     * Send JSON payload directly to local IPC push socket port.
     */
    protected function sendIpcPayload(string $host, int $port, string $payload): bool
    {
        if ($port <= 0) {
            return false;
        }

        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if (!$fp) {
                return false;
            }

            stream_set_timeout($fp, 1);
            @fwrite($fp, $payload . "\n");
            $response = @fread($fp, 512);
            @fclose($fp);

            return !empty($response);
        } catch (Throwable $e) {
            return false;
        }
    }
}
