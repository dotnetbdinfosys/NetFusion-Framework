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
 * Exception thrown when the WebAssembly runtime traps (e.g. division by zero,
 * out of bounds memory access, unreachable instruction, fuel exhaustion).
 */
class TrapException extends \RuntimeException
{
    public static function unreachable(): self
    {
        return new self('unreachable executed');
    }

    public static function integerDivideByZero(): self
    {
        return new self('integer divide by zero');
    }

    public static function integerOverflow(): self
    {
        return new self('integer overflow');
    }

    public static function memoryOutOfBounds(int $offset, int $length, int $totalBytes): self
    {
        return new self(sprintf(
            'out of bounds memory access: attempted to read/write %d bytes at offset %d (total memory: %d bytes)',
            $length,
            $offset,
            $totalBytes
        ));
    }

    public static function callStackExhausted(int $depth): self
    {
        return new self("call stack exhausted (max depth {$depth})");
    }

    public static function fuelExhausted(): self
    {
        return new self('all execution fuel exhausted');
    }
}
