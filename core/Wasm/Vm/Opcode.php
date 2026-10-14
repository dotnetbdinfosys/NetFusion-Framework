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

namespace NetFusion\Core\Wasm\Vm;

/**
 * WebAssembly 1.0 bytecode opcodes.
 */
class Opcode
{
    // Control instructions
    public const UNREACHABLE = 0x00;
    public const NOP = 0x01;
    public const BLOCK = 0x02;
    public const LOOP = 0x03;
    public const IF = 0x04;
    public const ELSE = 0x05;
    public const END = 0x0B;
    public const BR = 0x0C;
    public const BR_IF = 0x0D;
    public const BR_TABLE = 0x0E;
    public const RETURN = 0x0F;
    public const CALL = 0x10;
    public const CALL_INDIRECT = 0x11;

    // Parametric instructions
    public const DROP = 0x1A;
    public const SELECT = 0x1B;

    // Variable instructions
    public const LOCAL_GET = 0x20;
    public const LOCAL_SET = 0x21;
    public const LOCAL_TEE = 0x22;
    public const GLOBAL_GET = 0x23;
    public const GLOBAL_SET = 0x24;

    // Memory instructions
    public const I32_LOAD = 0x28;
    public const I64_LOAD = 0x29;
    public const F32_LOAD = 0x2A;
    public const F64_LOAD = 0x2B;
    public const I32_LOAD8_S = 0x2C;
    public const I32_LOAD8_U = 0x2D;
    public const I32_LOAD16_S = 0x2E;
    public const I32_LOAD16_U = 0x2F;
    public const I32_STORE = 0x36;
    public const I64_STORE = 0x37;
    public const F32_STORE = 0x38;
    public const F64_STORE = 0x39;
    public const I32_STORE8 = 0x3A;
    public const I32_STORE16 = 0x3B;
    public const MEMORY_SIZE = 0x3F;
    public const MEMORY_GROW = 0x40;

    // Numeric constants
    public const I32_CONST = 0x41;
    public const I64_CONST = 0x42;
    public const F32_CONST = 0x43;
    public const F64_CONST = 0x44;

    // Comparison instructions (i32)
    public const I32_EQZ = 0x45;
    public const I32_EQ = 0x46;
    public const I32_NE = 0x47;
    public const I32_LT_S = 0x48;
    public const I32_LT_U = 0x49;
    public const I32_GT_S = 0x4A;
    public const I32_GT_U = 0x4B;
    public const I32_LE_S = 0x4C;
    public const I32_LE_U = 0x4D;
    public const I32_GE_S = 0x4E;
    public const I32_GE_U = 0x4F;

    // Numeric instructions (i32)
    public const I32_CLZ = 0x67;
    public const I32_CTZ = 0x68;
    public const I32_POPCNT = 0x69;
    public const I32_ADD = 0x6A;
    public const I32_SUB = 0x6B;
    public const I32_MUL = 0x6C;
    public const I32_DIV_S = 0x6D;
    public const I32_DIV_U = 0x6E;
    public const I32_REM_S = 0x6F;
    public const I32_REM_U = 0x70;
    public const I32_AND = 0x71;
    public const I32_OR = 0x72;
    public const I32_XOR = 0x73;
    public const I32_SHL = 0x74;
    public const I32_SHR_S = 0x75;
    public const I32_SHR_U = 0x76;
    public const I32_ROTL = 0x77;
    public const I32_ROTR = 0x78;

    // Numeric instructions (i64)
    public const I64_ADD = 0x7C;
    public const I64_SUB = 0x7D;
    public const I64_MUL = 0x7E;
    public const I64_DIV_S = 0x7F;
    public const I64_DIV_U = 0x80;
    public const I64_EQ = 0x51;
    public const I64_NE = 0x52;

    public static function getName(int $opcode): string
    {
        static $names = null;
        if ($names === null) {
            $ref = new \ReflectionClass(self::class);
            $names = array_flip($ref->getConstants());
        }
        return $names[$opcode] ?? sprintf('UNKNOWN_0x%02X', $opcode);
    }
}
