<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/Frame.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets
| Purpose   : RFC 6455 WebSocket frame encoder and binary parser.
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

use InvalidArgumentException;
use NetFusion\Core\Sockets\Contracts\FrameInterface;

/**
 * Class Frame
 *
 * Implements RFC 6455 frame protocol encoding, decoding, and masking.
 *
 * @package NetFusion\Core\Sockets
 */
class Frame implements FrameInterface
{
    public function __construct(
        protected string $payload,
        protected int $opcode = self::OPCODE_TEXT,
        protected bool $isFinal = true,
        protected bool $isMasked = false,
        protected ?string $maskingKey = null
    ) {}

    /**
     * Get frame opcode.
     */
    public function getOpcode(): int
    {
        return $this->opcode;
    }

    /**
     * Get decoded payload data.
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Check if frame is final (FIN bit set).
     */
    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    /**
     * Check if frame was masked.
     */
    public function isMasked(): bool
    {
        return $this->isMasked;
    }

    /**
     * Check if frame is a control frame.
     */
    public function isControl(): bool
    {
        return $this->opcode >= 0x8;
    }

    /**
     * Get close status code and reason if this is a close frame.
     *
     * @return array{code: int, reason: string}
     */
    public function getCloseStatus(): array
    {
        if ($this->opcode !== self::OPCODE_CLOSE || strlen($this->payload) < 2) {
            return ['code' => 1000, 'reason' => ''];
        }

        $code = unpack('n', substr($this->payload, 0, 2))[1] ?? 1000;
        $reason = substr($this->payload, 2);

        return ['code' => $code, 'reason' => $reason];
    }

    /**
     * Encode this frame to raw binary wire data.
     */
    public function encode(): string
    {
        return self::encodeData($this->payload, $this->opcode, $this->isMasked, $this->isFinal);
    }

    /**
     * Static helper to encode payload into RFC 6455 binary frame.
     */
    public static function encodeData(string $payload, int $opcode = self::OPCODE_TEXT, bool $masked = false, bool $fin = true): string
    {
        $firstByte = ($fin ? 0x80 : 0x00) | ($opcode & 0x0F);
        $length = strlen($payload);
        $header = chr($firstByte);

        $maskBit = $masked ? 0x80 : 0x00;

        if ($length <= 125) {
            $header .= chr($maskBit | $length);
        } elseif ($length <= 65535) {
            $header .= chr($maskBit | 126) . pack('n', $length);
        } else {
            // 64-bit length
            $header .= chr($maskBit | 127) . pack('J', $length);
        }

        if ($masked) {
            $maskingKey = random_bytes(4);
            $header .= $maskingKey;
            $maskedPayload = '';
            for ($i = 0; $i < $length; $i++) {
                $maskedPayload .= $payload[$i] ^ $maskingKey[$i % 4];
            }
            return $header . $maskedPayload;
        }

        return $header . $payload;
    }

    /**
     * Decode a raw binary buffer into a Frame instance.
     * Returns null if buffer does not contain a full complete frame yet.
     * Updates $consumedBytes with the total number of bytes processed.
     */
    public static function decode(string $buffer, ?int &$consumedBytes = 0): ?self
    {
        $bufferLength = strlen($buffer);
        if ($bufferLength < 2) {
            $consumedBytes = 0;
            return null;
        }

        $firstByte = ord($buffer[0]);
        $secondByte = ord($buffer[1]);

        $fin = ($firstByte & 0x80) === 0x80;
        $opcode = $firstByte & 0x0F;
        $isMasked = ($secondByte & 0x80) === 0x80;
        $payloadLen = $secondByte & 0x7F;

        $offset = 2;

        if ($payloadLen === 126) {
            if ($bufferLength < $offset + 2) {
                $consumedBytes = 0;
                return null;
            }
            $payloadLen = unpack('n', substr($buffer, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLen === 127) {
            if ($bufferLength < $offset + 8) {
                $consumedBytes = 0;
                return null;
            }
            $payloadLen = unpack('J', substr($buffer, $offset, 8))[1];
            $offset += 8;
        }

        $maskingKey = null;
        if ($isMasked) {
            if ($bufferLength < $offset + 4) {
                $consumedBytes = 0;
                return null;
            }
            $maskingKey = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($bufferLength < $offset + $payloadLen) {
            // Frame is incomplete, wait for more data
            $consumedBytes = 0;
            return null;
        }

        $rawPayload = substr($buffer, $offset, $payloadLen);
        $consumedBytes = $offset + $payloadLen;

        $payload = '';
        if ($isMasked && $maskingKey !== null) {
            // Unmask payload
            for ($i = 0; $i < $payloadLen; $i++) {
                $payload .= $rawPayload[$i] ^ $maskingKey[$i % 4];
            }
        } else {
            $payload = $rawPayload;
        }

        return new self(
            payload: $payload,
            opcode: $opcode,
            isFinal: $fin,
            isMasked: $isMasked,
            maskingKey: $maskingKey
        );
    }

    /**
     * Create a text frame.
     */
    public static function text(string $text, bool $masked = false): self
    {
        return new self($text, self::OPCODE_TEXT, isFinal: true, isMasked: $masked);
    }

    /**
     * Create a binary frame.
     */
    public static function binary(string $data, bool $masked = false): self
    {
        return new self($data, self::OPCODE_BINARY, isFinal: true, isMasked: $masked);
    }

    /**
     * Create a ping frame.
     */
    public static function ping(string $payload = '', bool $masked = false): self
    {
        return new self($payload, self::OPCODE_PING, isFinal: true, isMasked: $masked);
    }

    /**
     * Create a pong frame.
     */
    public static function pong(string $payload = '', bool $masked = false): self
    {
        return new self($payload, self::OPCODE_PONG, isFinal: true, isMasked: $masked);
    }

    /**
     * Create a close frame.
     */
    public static function close(int $code = 1000, string $reason = '', bool $masked = false): self
    {
        $payload = pack('n', $code) . $reason;
        return new self($payload, self::OPCODE_CLOSE, isFinal: true, isMasked: $masked);
    }
}
