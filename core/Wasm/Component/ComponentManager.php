<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/ComponentManager.php
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

namespace NetFusion\Core\Wasm\Component;

use NetFusion\Core\Wasm\Component\Linker\ComponentOrchestrator;
use NetFusion\Core\Wasm\Component\Linker\VirtualLinker;
use NetFusion\Core\Wasm\Component\Model\ComponentInstance;
use NetFusion\Core\Wasm\Component\Model\ComponentModule;
use NetFusion\Core\Wasm\Component\WasiP2\WasiP2Runtime;
use NetFusion\Core\Wasm\Component\Wit\Ast\WitParser;

/**
 * Enterprise WebAssembly Component Model & WIT Master Facade.
 */
class ComponentManager
{
    private static ?self $instance = null;

    private WitParser $parser;
    private VirtualLinker $linker;
    private WasiP2Runtime $wasiP2;
    private ComponentOrchestrator $orchestrator;
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
        $this->config = array_merge([
            'default_memory_pages' => 2,
            'max_memory_pages'     => 16,
            'enable_wasi_p2'       => true,
        ], $config);

        $this->parser       = new WitParser();
        $this->linker       = new VirtualLinker();
        $this->wasiP2       = new WasiP2Runtime();
        $this->orchestrator = new ComponentOrchestrator($this->linker, $this->wasiP2);
    }

    public function getParser(): WitParser
    {
        return $this->parser;
    }

    public function getLinker(): VirtualLinker
    {
        return $this->linker;
    }

    public function getWasiP2(): WasiP2Runtime
    {
        return $this->wasiP2;
    }

    public function getOrchestrator(): ComponentOrchestrator
    {
        return $this->orchestrator;
    }

    /**
     * Parse WIT source text into abstract interfaces and worlds.
     */
    public function parseWit(string $witContent): array
    {
        return $this->parser->parse($witContent);
    }

    /**
     * Create a new ComponentModule container.
     */
    public function newModule(string $name, string $version = '0.1.0'): ComponentModule
    {
        $mod = new ComponentModule($name, $version);
        $this->orchestrator->registerModule($mod);
        return $mod;
    }

    /**
     * Instantiate a component module.
     */
    public function instantiate(string $moduleName, array $imports = []): ComponentInstance
    {
        return $this->orchestrator->instantiate($moduleName, $imports);
    }

    /**
     * Execute a multi-module pipeline.
     */
    public function pipeline(array $steps, mixed $initialInput): mixed
    {
        return $this->orchestrator->executePipeline($steps, $initialInput);
    }
}
