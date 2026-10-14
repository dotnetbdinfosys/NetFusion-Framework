<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/Contracts/ConnectionInterface.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets\Contracts
| Purpose   : Interface for active WebSocket client connections.
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

namespace NetFusion\Core\Sockets\Contracts;

/**
 * Interface ConnectionInterface
 *
 * Represents an active client connection on the WebSocket server.
 *
 * @package NetFusion\Core\Sockets\Contracts
 */
interface ConnectionInterface
{
    /**
     * Get unique connection ID.
     */
    public function getId(): string;

    /**
     * Get raw stream resource.
     *
     * @return resource
     */
    public function getSocket(): mixed;

    /**
     * Get remote IP address.
     */
    public function getRemoteAddress(): string;

    /**
     * Get remote port.
     */
    public function getRemotePort(): int;

    /**
     * Check if handshake is completed.
     */
    public function isHandshakeCompleted(): bool;

    /**
     * Mark handshake completed.
     */
    public function setHandshakeCompleted(bool $completed = true): void;

    /**
     * Get request headers from handshake.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Get query parameters from handshake URI.
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array;

    /**
     * Get handshake request path.
     */
    public function getPath(): string;

    /**
     * Send raw text message to client.
     */
    public function send(string $message): bool;

    /**
     * Send binary frame to client.
     */
    public function sendBinary(string $binaryData): bool;

    /**
     * Send ping frame to client.
     */
    public function ping(string $payload = ''): bool;

    /**
     * Send pong frame to client.
     */
    public function pong(string $payload = ''): bool;

    /**
     * Close connection gracefully with status code.
     */
    public function close(int $code = 1000, string $reason = ''): void;

    /**
     * Get list of subscribed channel names.
     *
     * @return array<string>
     */
    public function getChannels(): array;

    /**
     * Subscribe to a channel.
     */
    public function subscribe(string $channel): void;

    /**
     * Unsubscribe from a channel.
     */
    public function unsubscribe(string $channel): void;

    /**
     * Check if subscribed to channel.
     */
    public function isSubscribed(string $channel): bool;

    /**
     * Set custom attribute.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Get custom attribute.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if custom attribute exists.
     */
    public function has(string $key): bool;

    /**
     * Get last active timestamp.
     */
    public function getLastActiveAt(): int;

    /**
     * Touch last active timestamp.
     */
    public function touch(): void;
}
