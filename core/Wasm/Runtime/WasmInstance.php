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

namespace NetFusion\Core\Wasm\Runtime;

use NetFusion\Core\Wasm\Module\WasmModule;
use NetFusion\Core\Wasm\Vm\LinearMemory;
use NetFusion\Core\Wasm\Vm\VirtualMachine;
use NetFusion\Core\Wasm\Wasi\WasiSnapshotPreview1;

/**
 * Instantiated, isolated WebAssembly execution context.
 */
class WasmInstance
{
    private WasmModule $module;
    private VirtualMachine $vm;
    private ?LinearMemory $memory;
    private ?WasiSnapshotPreview1 $wasi;
    private FuelMeter $fuelMeter;

    public function __construct(
        WasmModule $module,
        ?LinearMemory $memory = null,
        ?WasiSnapshotPreview1 $wasi = null,
        array $hostFunctions = [],
        ?FuelMeter $fuelMeter = null
    ) {
        $this->module = $module;
        $this->wasi = $wasi;
        $this->fuelMeter = $fuelMeter ?? new FuelMeter(1_000_000);

        // Bind WASI functions if provided
        if ($wasi !== null) {
            $wasiFuncs = $wasi->getFunctions($memory);
            foreach ($wasiFuncs as $name => $fn) {
                $hostFunctions["{$wasi->getModuleName()}.{$name}"] = $fn;
            }
        }

        $this->vm = new VirtualMachine(
            module: $module,
            memory: $memory,
            hostFunctions: $hostFunctions,
            fuelMeter: $this->fuelMeter
        );

        $this->memory = $this->vm->getMemory();
    }

    public function getModule(): WasmModule
    {
        return $this->module;
    }

    public function getMemory(): ?LinearMemory
    {
        return $this->memory;
    }

    public function getWasi(): ?WasiSnapshotPreview1
    {
        return $this->wasi;
    }

    public function getStdout(): string
    {
        return $this->wasi ? $this->wasi->getStdout() : '';
    }

    public function getStderr(): string
    {
        return $this->wasi ? $this->wasi->getStderr() : '';
    }

    public function getFuelConsumed(): int
    {
        return $this->fuelMeter->getConsumedFuel();
    }

    public function getRemainingFuel(): int
    {
        return $this->fuelMeter->getRemainingFuel();
    }

    /**
     * Invoke an exported Wasm function.
     */
    public function invoke(string $functionName, array $args = []): mixed
    {
        return $this->vm->invoke($functionName, $args);
    }

    /**
     * Alias for invoke().
     */
    public function call(string $functionName, array $args = []): mixed
    {
        return $this->invoke($functionName, $args);
    }

    /**
     * List all public export names and their kinds.
     *
     * @return array<string, string> [ 'name' => 'function'|'memory'|'table'|'global' ]
     */
    public function getExports(): array
    {
        $res = [];
        foreach ($this->module->exports as $name => $exp) {
            $res[$name] = match ($exp->kind) {
                0 => 'function',
                1 => 'table',
                2 => 'memory',
                3 => 'global',
                default => 'unknown',
            };
        }
        return $res;
    }
}
