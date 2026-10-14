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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Wasm\WasmManager;

/**
 * Class WasmRunCommand
 *
 * CLI: php netfusion wasm:run <module.wasm> [function] [arg1 arg2 ...] [--fuel=N]
 */
class WasmRunCommand extends Command
{
    protected string $name = 'wasm:run';
    protected string $description = 'Execute a WebAssembly binary function in the sandboxed runtime';

    public function handle(array $args = []): int
    {
        $filePath = null;
        $funcName = '_start';
        $fuel = 1_000_000;
        $funcArgs = [];

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--fuel=')) {
                $fuel = (int) substr($arg, 7);
            } elseif ($filePath === null && !str_starts_with($arg, '--')) {
                $filePath = $arg;
            } elseif ($funcName === '_start' && !str_starts_with($arg, '--')) {
                $funcName = $arg;
            } elseif (!str_starts_with($arg, '--')) {
                // Parse integer or string argument
                $funcArgs[] = is_numeric($arg) ? (int) $arg : $arg;
            }
        }

        if ($filePath === null) {
            $this->error("Usage: php netfusion wasm:run <module.wasm> [function] [arg1 arg2 ...]");
            return 1;
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $wasm = wasm();
        $this->info("Loading Wasm module: {$filePath}");

        try {
            $module = $wasm->loadFile($filePath);
            $this->info("Executing function '{$funcName}' with fuel limit {$fuel} ...");

            $sandbox = $wasm->sandbox($module, $funcName, $funcArgs, $fuel);

            if ($sandbox['success']) {
                $this->success("Execution succeeded!");
                if ($sandbox['result'] !== null) {
                    $this->line("Return Value : " . var_export($sandbox['result'], true));
                }
                if (!empty($sandbox['stdout'])) {
                    $this->line("Standard Output:\n" . $sandbox['stdout']);
                }
                $this->line("Fuel Consumed: {$sandbox['fuelConsumed']}");
                $this->line("Duration     : {$sandbox['durationMs']} ms");
                return 0;
            }

            $this->error("Execution trapped/failed: {$sandbox['error']}");
            if (!empty($sandbox['stderr'])) {
                $this->error("Standard Error: " . $sandbox['stderr']);
            }
            return 1;
        } catch (\Throwable $e) {
            $this->error("Failed to run Wasm module: {$e->getMessage()}");
            return 1;
        }
    }
}
