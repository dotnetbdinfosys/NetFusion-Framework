<?php

declare(strict_types=1);

namespace NetFusion\Core\SchemaRegistry\SerDe;

use InvalidArgumentException;

class Varint
{
    /**
     * Encode unsigned integer as LEB128 Varint bytes.
     */
    public static function encode(int $value): string
    {
        $out = '';
        while ($value > 0x7F) {
            $out .= chr(($value & 0x7F) | 0x80);
            $value >>= 7;
        }
        $out .= chr($value & 0x7F);

        return $out;
    }

    /**
     * Decode LEB128 Varint from buffer starting at offset.
     */
    public static function decode(string $buffer, int &$offset = 0): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($buffer);

        while ($offset < $length) {
            $byte = ord($buffer[$offset++]);
            $result |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                return $result;
            }

            $shift += 7;
            if ($shift >= 64) {
                throw new InvalidArgumentException('Varint exceeds 64-bit bounds');
            }
        }

        throw new InvalidArgumentException('Truncated varint stream');
    }

    /**
     * ZigZag encode a signed 32-bit integer.
     */
    public static function encodeZigZag32(int $n): int
    {
        return (($n << 1) ^ ($n >> 31)) & 0xFFFFFFFF;
    }

    /**
     * ZigZag decode a signed 32-bit integer.
     */
    public static function decodeZigZag32(int $n): int
    {
        return (($n >> 1) ^ -($n & 1));
    }

    /**
     * ZigZag encode a signed 64-bit integer.
     */
    public static function encodeZigZag64(int $n): int
    {
        return ($n << 1) ^ ($n >> 63);
    }

    /**
     * ZigZag decode a signed 64-bit integer.
     */
    public static function decodeZigZag64(int $n): int
    {
        return ($n >> 1) ^ -($n & 1);
    }
}
