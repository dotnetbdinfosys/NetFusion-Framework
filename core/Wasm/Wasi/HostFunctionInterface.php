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

namespace NetFusion\Core\Wasm\Wasi;

use NetFusion\Core\Wasm\Vm\LinearMemory;

/**
 * Interface for host function modules that bind into the Wasm virtual machine.
 */
interface HostFunctionInterface
{
    /**
     * Get module namespace (e.g. "wasi_snapshot_preview1", "env").
     */
    public function getModuleName(): string;

    /**
     * Get map of exported host functions [ 'func_name' => callable ].
     *
     * @param LinearMemory|null $memory
     * @return array<string, callable>
     */
    public function getFunctions(?LinearMemory $memory = null): array;
}
