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

namespace NetFusion\Core\Wasm;

use NetFusion\Core\Wasm\Binary\ModuleParser;
use NetFusion\Core\Wasm\Module\WasmModule;
use NetFusion\Core\Wasm\Runtime\EdgeSandbox;
use NetFusion\Core\Wasm\Runtime\FuelMeter;
use NetFusion\Core\Wasm\Runtime\WasmInstance;
use NetFusion\Core\Wasm\Vm\LinearMemory;
use NetFusion\Core\Wasm\Wasi\WasiSnapshotPreview1;

/**
 * Enterprise WebAssembly Manager Facade.
 */
class WasmManager
{
    private static ?self $instance = null;

    private ModuleParser $parser;
    /** @var array<string, WasmModule> */
    private array $moduleCache = [];
    /** @var array<string, callable> */
    private array $hostFunctions = [];
    private array $config;

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function __construct(array $config = [])
    {
        $this->parser = new ModuleParser();
        $this->config = array_merge([
            'default_fuel' => 1_000_000,
            'max_memory_pages' => 64, // 4 MB
            'enable_wasi' => true,
        ], $config);
    }

    public function getParser(): ModuleParser
    {
        return $this->parser;
    }

    /**
     * Parse binary string into WasmModule and optionally cache under a key.
     */
    public function parse(string $binary, ?string $cacheKey = null): WasmModule
    {
        $module = $this->parser->parse($binary);
        if ($cacheKey !== null) {
            $this->moduleCache[$cacheKey] = $module;
        }
        return $module;
    }

    /**
     * Load Wasm module from file path with caching.
     */
    public function loadFile(string $filePath): WasmModule
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("WebAssembly file not found: {$filePath}");
        }

        $realPath = realpath($filePath) ?: $filePath;
        if (isset($this->moduleCache[$realPath])) {
            return $this->moduleCache[$realPath];
        }

        $binary = (string) file_get_contents($realPath);
        $module = $this->parser->parse($binary);
        $this->moduleCache[$realPath] = $module;

        return $module;
    }

    /**
     * Register a globally available host function across all instances.
     */
    public function registerHostFunction(string $module, string $name, callable $handler): self
    {
        $this->hostFunctions["{$module}.{$name}"] = $handler;
        return $this;
    }

    /**
     * Instantiate a WasmModule into an isolated WasmInstance.
     */
    public function instantiate(
        WasmModule|string $moduleOrPath,
        array $hostFunctions = [],
        ?int $fuel = null,
        array $env = []
    ): WasmInstance {
        $module = is_string($moduleOrPath) ? $this->loadFile($moduleOrPath) : $moduleOrPath;
        $mergedHost = array_merge($this->hostFunctions, $hostFunctions);

        $wasi = $this->config['enable_wasi'] ? new WasiSnapshotPreview1($env) : null;
        $fuelMeter = new FuelMeter($fuel ?? $this->config['default_fuel'], true);

        return new WasmInstance(
            module: $module,
            wasi: $wasi,
            hostFunctions: $mergedHost,
            fuelMeter: $fuelMeter
        );
    }

    /**
     * Quick invocation of an exported function in a module.
     */
    public function run(WasmModule|string $moduleOrPath, string $functionName, array $args = []): mixed
    {
        $instance = $this->instantiate($moduleOrPath);
        return $instance->invoke($functionName, $args);
    }

    /**
     * Execute in sandboxed edge serverless container with metrics.
     */
    public function sandbox(
        WasmModule|string $moduleOrPath,
        string $functionName,
        array $args = [],
        ?int $fuel = null
    ): array {
        $module = is_string($moduleOrPath) ? $this->loadFile($moduleOrPath) : $moduleOrPath;
        $edge = new EdgeSandbox(
            maxFuel: $fuel ?? $this->config['default_fuel'],
            maxMemoryPages: $this->config['max_memory_pages']
        );

        return $edge->execute($module, $functionName, $args, $this->hostFunctions);
    }
}
