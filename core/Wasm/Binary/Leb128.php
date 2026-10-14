<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Wasm\Binary;

/**
 * Pure PHP Little-Endian Base 128 (LEB128) Variable-Length Integer SerDe.
 * Handles both Unsigned (ULEB128) and Signed (SLEB128) formats.
 */
class Leb128
{
    /**
     * Decode Unsigned LEB128 from binary string starting at $offset.
     *
     * @param string $bytes
     * @param int $offset (passed by reference, advanced by bytes read)
     * @param int $maxBits
     * @return int
     */
    public static function decodeUint(string $bytes, int &$offset, int $maxBits = 32): int
    {
        $result = 0;
        $shift = 0;
        $len = strlen($bytes);

        while (true) {
            if ($offset >= $len) {
                throw new \UnderflowException("Unexpected EOF while decoding ULEB128 integer at offset {$offset}.");
            }

            $byte = ord($bytes[$offset++]);
            $result |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                break;
            }

            $shift += 7;
            if ($shift >= $maxBits) {
                throw new \OverflowException("ULEB128 sequence exceeded maximum {$maxBits} bit length.");
            }
        }

        return $result;
    }

    /**
     * Decode Signed LEB128 from binary string starting at $offset.
     *
     * @param string $bytes
     * @param int $offset (passed by reference, advanced by bytes read)
     * @param int $maxBits
     * @return int
     */
    public static function decodeInt(string $bytes, int &$offset, int $maxBits = 32): int
    {
        $result = 0;
        $shift = 0;
        $len = strlen($bytes);

        while (true) {
            if ($offset >= $len) {
                throw new \UnderflowException("Unexpected EOF while decoding SLEB128 integer at offset {$offset}.");
            }

            $byte = ord($bytes[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;

            if (($byte & 0x80) === 0) {
                // Sign extend if sign bit of the last byte is set
                if ($shift < $maxBits && ($byte & 0x40) !== 0) {
                    $result |= -(1 << $shift);
                }
                break;
            }

            if ($shift >= $maxBits) {
                throw new \OverflowException("SLEB128 sequence exceeded maximum {$maxBits} bit length.");
            }
        }

        return $result;
    }

    /**
     * Encode unsigned integer into ULEB128 binary string.
     */
    public static function encodeUint(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("Cannot encode negative integer {$value} as unsigned LEB128.");
        }

        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $out .= chr($byte);
        } while ($value !== 0);

        return $out;
    }

    /**
     * Encode signed integer into SLEB128 binary string.
     */
    public static function encodeInt(int $value): string
    {
        $out = '';
        $more = true;

        while ($more) {
            $byte = $value & 0x7F;
            $value >>= 7; // Arithmetic right shift preserves sign in PHP

            $signBit = ($byte & 0x40) !== 0;

            if (($value === 0 && !$signBit) || ($value === -1 && $signBit)) {
                $more = false;
            } else {
                $byte |= 0x80;
            }

            $out .= chr($byte);
        }

        return $out;
    }
}
