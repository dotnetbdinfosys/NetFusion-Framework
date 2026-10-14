<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Linker/VirtualLinker.php
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

use NetFusion\Core\Wasm\Component\Exceptions\LinkerException;
use NetFusion\Core\Wasm\Component\Model\ComponentInstance;
use NetFusion\Core\Wasm\Component\Model\ComponentModule;
use NetFusion\Core\Wasm\Runtime\WasmInstance;

/**
 * Virtual Linker for resolving dependencies and instantiating multi-module components.
 */
class VirtualLinker
{
    /** @var array<string, callable> Host or external functions available for import */
    private array $hostFunctions = [];
    /** @var array<string, ComponentModule> Registered components */
    private array $registeredComponents = [];

    public function defineHostFunction(string $name, callable $fn): self
    {
        $this->hostFunctions[$name] = $fn;
        return $this;
    }

    public function registerComponent(ComponentModule $module): self
    {
        $this->registeredComponents[$module->getName()] = $module;
        return $this;
    }

    /**
     * Instantiate a component module, resolving its imports against provided instances or host functions.
     *
     * @param ComponentModule $module
     * @param array<string, ComponentInstance> $imports Instances to satisfy component imports
     * @param array<string, WasmInstance> $coreInstances Pre-instantiated core Wasm instances
     * @return ComponentInstance
     */
    public function instantiate(
        ComponentModule $module,
        array $imports = [],
        array $coreInstances = []
    ): ComponentInstance {
        $bound = [];

        // Verify all required imports
        foreach ($module->getImports() as $importName => $import) {
            $target = $import->getTarget();
            $targetStr = is_string($target) ? $target : $importName;

            if (isset($imports[$importName])) {
                $bound[$importName] = $imports[$importName];
            } elseif (isset($imports[$targetStr])) {
                $bound[$importName] = $imports[$targetStr];
            } elseif (isset($this->hostFunctions[$importName])) {
                $bound[$importName] = $this->hostFunctions[$importName];
            } elseif (isset($this->hostFunctions[$targetStr])) {
                $bound[$importName] = $this->hostFunctions[$targetStr];
            } else {
                throw new LinkerException("Unsatisfied import '{$importName}' in component '{$module->getName()}'.");
            }
        }

        return new ComponentInstance($module, $coreInstances, $bound);
    }

    /**
     * Compose two or more component modules into a single execution pipeline.
     */
    public function compose(
        ComponentModule $producer,
        ComponentModule $consumer,
        string $composedName
    ): ComponentModule {
        $composed = new ComponentModule($composedName);

        // Inherit core modules
        foreach ($producer->getCoreModules() as $k => $m) {
            $composed->addCoreModule("producer_{$k}", $m);
        }
        foreach ($consumer->getCoreModules() as $k => $m) {
            $composed->addCoreModule("consumer_{$k}", $m);
        }

        // Connect exports of producer that match imports of consumer
        foreach ($consumer->getImports() as $reqName => $reqImport) {
            if (!$producer->getExport($reqName)) {
                // Keep as external import
                $composed->addImport($reqImport);
            }
        }

        // Exports of consumer become exports of the composed pipeline
        foreach ($consumer->getExports() as $expName => $exp) {
            $composed->addExport($exp);
        }

        return $composed;
    }
}
