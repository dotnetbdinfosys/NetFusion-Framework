<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Auth\WebAuthn\Cbor;

use InvalidArgumentException;

class CborDecoder
{
    /**
     * Decode CBOR binary payload into PHP data structures.
     *
     * @param string $data
     * @param int    $offset
     * @return mixed
     */
    public static function decode(string $data, ?int &$offset = 0): mixed
    {
        $offset = $offset ?? 0;
        if ($offset >= strlen($data)) {
            throw new InvalidArgumentException("CBOR decode error: unexpected end of buffer at offset {$offset}.");
        }

        $initialByte = ord($data[$offset++]);
        $majorType = ($initialByte >> 5) & 0x07;
        $additionalInfo = $initialByte & 0x1f;

        $val = self::readLengthOrValue($data, $additionalInfo, $offset);

        return match ($majorType) {
            0 => $val,                                    // Unsigned integer
            1 => -1 - $val,                               // Negative integer
            2 => self::readByteString($data, $val, $offset), // Byte string
            3 => self::readTextString($data, $val, $offset), // Text string
            4 => self::readArray($data, $val, $offset),   // Array
            5 => self::readMap($data, $val, $offset),     // Map
            6 => self::decode($data, $offset),            // Tagged item (skip tag and return item)
            7 => self::readSimpleValue($additionalInfo, $val), // Simple / Float
            default => throw new InvalidArgumentException("Unknown CBOR major type: {$majorType}"),
        };
    }

    protected static function readLengthOrValue(string $data, int $info, int &$offset): int|float
    {
        if ($info < 24) {
            return $info;
        }

        return match ($info) {
            24 => self::readUint8($data, $offset),
            25 => self::readUint16($data, $offset),
            26 => self::readUint32($data, $offset),
            27 => self::readUint64($data, $offset),
            default => throw new InvalidArgumentException("Unsupported CBOR additional info: {$info}"),
        };
    }

    protected static function readUint8(string $data, int &$offset): int
    {
        if ($offset + 1 > strlen($data)) {
            throw new InvalidArgumentException("CBOR unexpected EOF reading uint8 at {$offset}");
        }
        return ord($data[$offset++]);
    }

    protected static function readUint16(string $data, int &$offset): int
    {
        if ($offset + 2 > strlen($data)) {
            throw new InvalidArgumentException("CBOR unexpected EOF reading uint16 at {$offset}");
        }
        $val = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        return $val;
    }

    protected static function readUint32(string $data, int &$offset): int
    {
        if ($offset + 4 > strlen($data)) {
            throw new InvalidArgumentException("CBOR unexpected EOF reading uint32 at {$offset}");
        }
        $val = unpack('N', substr($data, $offset, 4))[1];
        $offset += 4;
        return $val;
    }

    protected static function readUint64(string $data, int &$offset): int
    {
        if ($offset + 8 > strlen($data)) {
            throw new InvalidArgumentException("CBOR unexpected EOF reading uint64 at {$offset}");
        }
        $val = unpack('J', substr($data, $offset, 8))[1];
        $offset += 8;
        return $val;
    }

    protected static function readByteString(string $data, int $length, int &$offset): string
    {
        if ($offset + $length > strlen($data)) {
            throw new InvalidArgumentException("CBOR unexpected EOF reading byte string (length: {$length})");
        }
        $str = substr($data, $offset, $length);
        $offset += $length;
        return $str;
    }

    protected static function readTextString(string $data, int $length, int &$offset): string
    {
        return self::readByteString($data, $length, $offset);
    }

    protected static function readArray(string $data, int $length, int &$offset): array
    {
        $arr = [];
        for ($i = 0; $i < $length; $i++) {
            $arr[] = self::decode($data, $offset);
        }
        return $arr;
    }

    protected static function readMap(string $data, int $length, int &$offset): array
    {
        $map = [];
        for ($i = 0; $i < $length; $i++) {
            $key = self::decode($data, $offset);
            $val = self::decode($data, $offset);
            $map[$key] = $val;
        }
        return $map;
    }

    protected static function readSimpleValue(int $info, int|float $val): mixed
    {
        return match ($info) {
            20 => false,
            21 => true,
            22 => null,
            23 => null, // undefined
            default => $val,
        };
    }
}
