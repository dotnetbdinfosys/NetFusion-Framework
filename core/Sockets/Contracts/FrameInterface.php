<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/Contracts/FrameInterface.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets\Contracts
| Purpose   : Interface for WebSocket RFC 6455 protocol frames.
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
 * Interface FrameInterface
 *
 * Contract for parsing and encoding RFC 6455 WebSocket frames.
 *
 * @package NetFusion\Core\Sockets\Contracts
 */
interface FrameInterface
{
    public const OPCODE_CONTINUATION = 0x0;
    public const OPCODE_TEXT         = 0x1;
    public const OPCODE_BINARY       = 0x2;
    public const OPCODE_CLOSE        = 0x8;
    public const OPCODE_PING         = 0x9;
    public const OPCODE_PONG         = 0xA;

    /**
     * Get frame opcode.
     */
    public function getOpcode(): int;

    /**
     * Get decoded payload data.
     */
    public function getPayload(): string;

    /**
     * Check if frame is final (FIN bit set).
     */
    public function isFinal(): bool;

    /**
     * Check if frame was masked.
     */
    public function isMasked(): bool;

    /**
     * Check if frame is a control frame (close, ping, pong).
     */
    public function isControl(): bool;

    /**
     * Encode this frame to raw binary string for wire transmission.
     */
    public function encode(): string;
}
