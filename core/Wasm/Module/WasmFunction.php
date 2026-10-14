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

use NetFusion\Core\Wasm\Types\ValueType;

/**
 * Concrete WebAssembly function definition containing its type index, local variables, and raw bytecode.
 */
class WasmFunction
{
    /**
     * @param int $typeIndex
     * @param array<ValueType> $locals
     * @param string $bytecode
     * @param string|null $debugName
     */
    public function __construct(
        public readonly int $typeIndex,
        public readonly array $locals = [],
        public readonly string $bytecode = '',
        public readonly ?string $debugName = null
    ) {
    }

    public function getLocalCount(): int
    {
        return count($this->locals);
    }

    public function getBytecodeLength(): int
    {
        return strlen($this->bytecode);
    }
}
