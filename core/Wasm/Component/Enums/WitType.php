<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Enums/WitType.php
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

namespace NetFusion\Core\Wasm\Component\Enums;

/**
 * WebAssembly Interface Types (WIT) Primitive & Composite Type Identifiers.
 */
enum WitType: string
{
    case BOOL     = 'bool';
    case U8       = 'u8';
    case U16      = 'u16';
    case U32      = 'u32';
    case U64      = 'u64';
    case S8       = 's8';
    case S16      = 's16';
    case S32      = 's32';
    case S64      = 's64';
    case F32      = 'f32';
    case F64      = 'f64';
    case CHAR     = 'char';
    case STRING   = 'string';
    case LIST     = 'list';
    case RECORD   = 'record';
    case VARIANT  = 'variant';
    case ENUM     = 'enum';
    case OPTION   = 'option';
    case RESULT   = 'result';
    case TUPLE    = 'tuple';
    case FLAGS    = 'flags';
    case RESOURCE = 'resource';

    public function isInteger(): bool
    {
        return match ($this) {
            self::U8, self::U16, self::U32, self::U64,
            self::S8, self::S16, self::S32, self::S64 => true,
            default => false,
        };
    }

    public function isFloat(): bool
    {
        return match ($this) {
            self::F32, self::F64 => true,
            default => false,
        };
    }

    public function isCompound(): bool
    {
        return match ($this) {
            self::LIST, self::RECORD, self::VARIANT, self::ENUM,
            self::OPTION, self::RESULT, self::TUPLE, self::FLAGS,
            self::RESOURCE => true,
            default => false,
        };
    }
}
