<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Abi/ValType.php
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

namespace NetFusion\Core\Wasm\Component\Abi;

/**
 * Type descriptor and size/alignment calculator for Canonical ABI types.
 */
class ValType
{
    public static function size(string $type): int
    {
        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'bool', 'u8', 's8'   => 1,
            'u16', 's16'        => 2,
            'u32', 's32', 'f32', 'char' => 4,
            'u64', 's64', 'f64' => 8,
            'string'            => 8, // ptr (4) + len (4)
            default => str_starts_with($normalized, 'list<') ? 8 : 4,
        };
    }

    public static function alignment(string $type): int
    {
        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'bool', 'u8', 's8'   => 1,
            'u16', 's16'        => 2,
            'u32', 's32', 'f32', 'char' => 4,
            'u64', 's64', 'f64' => 8,
            'string'            => 4,
            default => 4,
        };
    }

    public static function alignTo(int $offset, int $alignment): int
    {
        return (int)(ceil($offset / $alignment) * $alignment);
    }
}
