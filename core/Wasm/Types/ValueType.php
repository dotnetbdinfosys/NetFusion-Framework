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

namespace NetFusion\Core\Wasm\Types;

/**
 * WebAssembly 1.0 value types encoded as single-byte values in binary format.
 */
enum ValueType: int
{
    case I32 = 0x7F;       // -0x01 in signed LEB128
    case I64 = 0x7E;       // -0x02
    case F32 = 0x7D;       // -0x03
    case F64 = 0x7C;       // -0x04
    case V128 = 0x7B;      // Vector (SIMD)
    case FUNCREF = 0x70;   // Function reference
    case EXTERNREF = 0x6F; // External reference

    public function typeName(): string
    {
        return match ($this) {
            self::I32 => 'i32',
            self::I64 => 'i64',
            self::F32 => 'f32',
            self::F64 => 'f64',
            self::V128 => 'v128',
            self::FUNCREF => 'funcref',
            self::EXTERNREF => 'externref',
        };
    }

    public static function fromByte(int $byte): self
    {
        return self::tryFrom($byte) ?? throw new \InvalidArgumentException(sprintf('Unknown Wasm ValueType byte: 0x%02X', $byte));
    }
}
