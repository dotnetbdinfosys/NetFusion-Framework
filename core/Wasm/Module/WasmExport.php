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

namespace NetFusion\Core\Wasm\Module;

/**
 * WebAssembly export descriptor.
 */
class WasmExport
{
    public const KIND_FUNCTION = 0x00;
    public const KIND_TABLE = 0x01;
    public const KIND_MEMORY = 0x02;
    public const KIND_GLOBAL = 0x03;

    public function __construct(
        public readonly string $name,
        public readonly int $kind,
        public readonly int $index
    ) {
    }

    public function isFunction(): bool
    {
        return $this->kind === self::KIND_FUNCTION;
    }

    public function isMemory(): bool
    {
        return $this->kind === self::KIND_MEMORY;
    }

    public function isGlobal(): bool
    {
        return $this->kind === self::KIND_GLOBAL;
    }

    public function isTable(): bool
    {
        return $this->kind === self::KIND_TABLE;
    }
}
