<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/Connection.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets
| Purpose   : Active client connection wrapper with channel subscriptions.
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

use NetFusion\Core\Sockets\Contracts\ConnectionInterface;

/**
 * Class Connection
 *
 * Encapsulates an active client socket, its handshake state, attributes and subscriptions.
 *
 * @package NetFusion\Core\Sockets
 */
class Connection implements ConnectionInterface
{
    protected string $id;
    protected string $remoteAddress = '';
    protected int $remotePort = 0;
    protected bool $handshakeCompleted = false;
    protected array $headers = [];
    protected array $queryParams = [];
    protected string $path = '/';
    protected array $channels = [];
    protected array $attributes = [];
    protected int $lastActiveAt;
    protected string $readBuffer = '';

    /**
     * @param resource $socket
     */
    public function __construct(
        protected mixed $socket,
        ?string $id = null
    ) {
        $this->id = $id ?? bin2hex(random_bytes(16));
        $this->lastActiveAt = time();

        if (is_resource($this->socket)) {
            $name = @stream_socket_get_name($this->socket, true);
            if ($name !== false && is_string($name)) {
                $parts = explode(':', $name);
                $this->remotePort = (int) array_pop($parts);
                $this->remoteAddress = implode(':', $parts);
            }
        }
    }

    /**
     * Get unique connection ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get underlying socket resource.
     *
     * @return resource
     */
    public function getSocket(): mixed
    {
        return $this->socket;
    }

    /**
     * Get remote IP.
     */
    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }

    /**
     * Get remote port.
     */
    public function getRemotePort(): int
    {
        return $this->remotePort;
    }

    /**
     * Check if handshake completed.
     */
    public function isHandshakeCompleted(): bool
    {
        return $this->handshakeCompleted;
    }

    /**
     * Set handshake status.
     */
    public function setHandshakeCompleted(bool $completed = true): void
    {
        $this->handshakeCompleted = $completed;
        $this->touch();
    }

    /**
     * Set handshake request metadata.
     *
     * @param array<string, string> $headers
     * @param array<string, string> $queryParams
     */
    public function setHandshakeData(string $path, array $headers, array $queryParams): void
    {
        $this->path = $path;
        $this->headers = $headers;
        $this->queryParams = $queryParams;
    }

    /**
     * Get handshake headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get query params.
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Get request path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Send text frame to client.
     */
    public function send(string $message): bool
    {
        $frame = Frame::text($message, masked: false);
        return $this->writeRaw($frame->encode());
    }

    /**
     * Send binary frame to client.
     */
    public function sendBinary(string $binaryData): bool
    {
        $frame = Frame::binary($binaryData, masked: false);
        return $this->writeRaw($frame->encode());
    }

    /**
     * Send ping frame to client.
     */
    public function ping(string $payload = ''): bool
    {
        $frame = Frame::ping($payload, masked: false);
        return $this->writeRaw($frame->encode());
    }

    /**
     * Send pong frame to client.
     */
    public function pong(string $payload = ''): bool
    {
        $frame = Frame::pong($payload, masked: false);
        return $this->writeRaw($frame->encode());
    }

    /**
     * Close connection with RFC 6455 code & reason.
     */
    public function close(int $code = 1000, string $reason = ''): void
    {
        if (is_resource($this->socket)) {
            $frame = Frame::close($code, $reason, masked: false);
            @fwrite($this->socket, $frame->encode());
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    /**
     * Write raw binary data directly to socket stream.
     */
    public function writeRaw(string $data): bool
    {
        if (!is_resource($this->socket)) {
            return false;
        }

        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($this->socket, substr($data, $written));
            if ($result === false || $result === 0) {
                return false;
            }
            $written += $result;
        }

        $this->touch();
        return true;
    }

    /**
     * Append to internal read buffer.
     */
    public function appendReadBuffer(string $data): void
    {
        $this->readBuffer .= $data;
        $this->touch();
    }

    /**
     * Get current read buffer.
     */
    public function getReadBuffer(): string
    {
        return $this->readBuffer;
    }

    /**
     * Consume N bytes from read buffer.
     */
    public function consumeReadBuffer(int $bytes): void
    {
        $this->readBuffer = substr($this->readBuffer, $bytes);
    }

    /**
     * Clear entire read buffer.
     */
    public function clearReadBuffer(): void
    {
        $this->readBuffer = '';
    }

    /**
     * Get subscribed channels.
     *
     * @return array<string>
     */
    public function getChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Subscribe to channel.
     */
    public function subscribe(string $channel): void
    {
        $this->channels[$channel] = true;
    }

    /**
     * Unsubscribe from channel.
     */
    public function unsubscribe(string $channel): void
    {
        unset($this->channels[$channel]);
    }

    /**
     * Check if subscribed to channel.
     */
    public function isSubscribed(string $channel): bool
    {
        return isset($this->channels[$channel]);
    }

    /**
     * Set custom attribute.
     */
    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get custom attribute.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if attribute exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get last active timestamp.
     */
    public function getLastActiveAt(): int
    {
        return $this->lastActiveAt;
    }

    /**
     * Touch active timestamp.
     */
    public function touch(): void
    {
        $this->lastActiveAt = time();
    }
}
