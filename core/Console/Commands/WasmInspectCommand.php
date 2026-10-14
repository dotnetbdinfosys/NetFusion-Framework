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
 * Class WasmInspectCommand
 *
 * CLI: php netfusion wasm:inspect <module.wasm>
 */
class WasmInspectCommand extends Command
{
    protected string $name = 'wasm:inspect';
    protected string $description = 'Disassemble and inspect WebAssembly binary module structure';

    public function handle(array $args = []): int
    {
        $filePath = null;
        foreach ($args as $arg) {
            if (is_string($arg) && !str_starts_with($arg, '--')) {
                $filePath = $arg;
                break;
            }
        }

        if ($filePath === null) {
            $this->error("Usage: php netfusion wasm:inspect <module.wasm>");
            return 1;
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $wasm = wasm();
        try {
            $module = $wasm->loadFile($filePath);
            $size = filesize($filePath);

            $this->info("=== WebAssembly Module Inspection: {$filePath} ===");
            $this->line("Format Version : {$module->version}");
            $this->line("File Size      : {$size} bytes");
            $this->line("Type Signatures: " . count($module->types));
            $this->line("Imports        : " . count($module->imports));
            $this->line("Functions      : " . count($module->functions));
            $this->line("Exports        : " . count($module->exports));
            $this->line("Memory Pages   : " . (!empty($module->memories) ? $module->memories[0]['initial'] : 0));

            if (!empty($module->imports)) {
                $this->line("\n[Imports]:");
                foreach ($module->imports as $imp) {
                    $kind = match ($imp->kind) {
                        0 => 'func',
                        1 => 'table',
                        2 => 'memory',
                        3 => 'global',
                        default => 'unknown',
                    };
                    $this->line("  - {$imp->module}.{$imp->name} ({$kind})");
                }
            }

            if (!empty($module->exports)) {
                $this->line("\n[Exports]:");
                foreach ($module->exports as $name => $exp) {
                    $kind = match ($exp->kind) {
                        0 => 'func',
                        1 => 'table',
                        2 => 'memory',
                        3 => 'global',
                        default => 'unknown',
                    };
                    $this->line("  - {$name} ({$kind}, index: {$exp->index})");
                }
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to inspect Wasm module: {$e->getMessage()}");
            return 1;
        }
    }
}
