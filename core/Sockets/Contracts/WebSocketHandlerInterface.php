<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/Contracts/WebSocketHandlerInterface.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets\Contracts
| Purpose   : Interface for WebSocket application lifecycle handlers.
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

use Throwable;

/**
 * Interface WebSocketHandlerInterface
 *
 * Handler callbacks invoked during connection lifecycle and message events.
 *
 * @package NetFusion\Core\Sockets\Contracts
 */
interface WebSocketHandlerInterface
{
    /**
     * Triggered when a new WebSocket client completes handshake and connects.
     */
    public function onOpen(ConnectionInterface $conn): void;

    /**
     * Triggered when a text message frame is received from a client.
     */
    public function onMessage(ConnectionInterface $conn, string $message): void;

    /**
     * Triggered when a binary message frame is received from a client.
     */
    public function onBinary(ConnectionInterface $conn, string $binaryData): void;

    /**
     * Triggered when a client connection closes.
     */
    public function onClose(ConnectionInterface $conn, int $code, string $reason): void;

    /**
     * Triggered when an error or exception occurs on a connection.
     */
    public function onError(ConnectionInterface $conn, Throwable $error): void;
}
