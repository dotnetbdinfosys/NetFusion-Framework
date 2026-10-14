<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Linker/ComponentOrchestrator.php
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

namespace NetFusion\Core\Wasm\Component\Linker;

use NetFusion\Core\Wasm\Component\Model\ComponentInstance;
use NetFusion\Core\Wasm\Component\Model\ComponentModule;
use NetFusion\Core\Wasm\Component\WasiP2\WasiP2Runtime;

/**
 * Multi-Module Component Orchestrator.
 * Coordinates multi-tenant component lifecycles, WASI Preview 2 bridges, and pipelines.
 */
class ComponentOrchestrator
{
    private VirtualLinker $linker;
    private WasiP2Runtime $wasiP2;
    /** @var array<string, ComponentModule> */
    private array $modules = [];
    /** @var array<string, ComponentInstance> */
    private array $instances = [];

    public function __construct(?VirtualLinker $linker = null, ?WasiP2Runtime $wasiP2 = null)
    {
        $this->linker = $linker ?? new VirtualLinker();
        $this->wasiP2 = $wasiP2 ?? new WasiP2Runtime();

        // Bind standard WASI Preview 2 host functions into linker
        foreach ($this->wasiP2->getHostFunctions() as $name => $fn) {
            $this->linker->defineHostFunction($name, $fn);
        }
    }

    public function getLinker(): VirtualLinker
    {
        return $this->linker;
    }

    public function getWasiP2(): WasiP2Runtime
    {
        return $this->wasiP2;
    }

    public function registerModule(ComponentModule $module): self
    {
        $this->modules[$module->getName()] = $module;
        $this->linker->registerComponent($module);
        return $this;
    }

    public function getModule(string $name): ?ComponentModule
    {
        return $this->modules[$name] ?? null;
    }

    public function instantiate(string $moduleName, array $importInstances = []): ComponentInstance
    {
        $module = $this->modules[$moduleName] ?? null;
        if ($module === null) {
            throw new \InvalidArgumentException("Component module '{$moduleName}' not registered.");
        }

        $instance = $this->linker->instantiate($module, $importInstances);
        $this->instances[$moduleName] = $instance;
        return $instance;
    }

    public function getInstance(string $moduleName): ?ComponentInstance
    {
        return $this->instances[$moduleName] ?? null;
    }

    /**
     * Run a pipeline across sequential component instances.
     * Passes the return value of each step as the argument to the next step.
     */
    public function executePipeline(array $steps, mixed $initialInput): mixed
    {
        $current = $initialInput;
        foreach ($steps as $step) {
            $moduleName = $step['module'];
            $funcName   = $step['func'];

            $instance = $this->instances[$moduleName] ?? $this->instantiate($moduleName);
            $current  = $instance->call($funcName, is_array($current) ? $current : [$current]);
        }
        return $current;
    }
}
