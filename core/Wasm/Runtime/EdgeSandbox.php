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
use NetFusion\Core\Wasm\Wasi\WasiSnapshotPreview1;

/**
 * Sandboxed Edge / Serverless Function Execution Runtime.
 */
class EdgeSandbox
{
    private int $maxFuel;
    private int $maxMemoryPages;
    private float $timeoutSeconds;
    private array $env;

    public function __construct(
        int $maxFuel = 500_000,
        int $maxMemoryPages = 16, // 16 pages = 1 MB
        float $timeoutSeconds = 5.0,
        array $env = []
    ) {
        $this->maxFuel = $maxFuel;
        $this->maxMemoryPages = $maxMemoryPages;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->env = $env;
    }

    /**
     * Execute a function inside the sandbox and capture telemetry metrics.
     *
     * @param WasmModule $module
     * @param string $functionName
     * @param array<mixed> $args
     * @param array<string, callable> $hostFunctions
     * @return array{
     *   success: bool,
     *   result: mixed,
     *   stdout: string,
     *   stderr: string,
     *   fuelConsumed: int,
     *   durationMs: float,
     *   error: ?string
     * }
     */
    public function execute(
        WasmModule $module,
        string $functionName,
        array $args = [],
        array $hostFunctions = []
    ): array {
        $start = microtime(true);
        $fuelMeter = new FuelMeter($this->maxFuel, true);

        // Bound memory pages
        $initialPages = !empty($module->memories) ? $module->memories[0]['initial'] : 1;
        $memory = new LinearMemory($initialPages, $this->maxMemoryPages);
        $wasi = new WasiSnapshotPreview1($this->env);

        try {
            $instance = new WasmInstance(
                module: $module,
                memory: $memory,
                wasi: $wasi,
                hostFunctions: $hostFunctions,
                fuelMeter: $fuelMeter
            );

            $result = $instance->invoke($functionName, $args);
            $durationMs = (microtime(true) - $start) * 1000.0;

            return [
                'success' => true,
                'result' => $result,
                'stdout' => $wasi->getStdout(),
                'stderr' => $wasi->getStderr(),
                'fuelConsumed' => $fuelMeter->getConsumedFuel(),
                'durationMs' => round($durationMs, 2),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000.0;
            return [
                'success' => false,
                'result' => null,
                'stdout' => $wasi->getStdout(),
                'stderr' => $wasi->getStderr(),
                'fuelConsumed' => $fuelMeter->getConsumedFuel(),
                'durationMs' => round($durationMs, 2),
                'error' => $e->getMessage(),
            ];
        }
    }
}
