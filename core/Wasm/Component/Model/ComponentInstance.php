<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Model/ComponentInstance.php
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

namespace NetFusion\Core\Wasm\Component\Model;

use NetFusion\Core\Wasm\Component\Abi\CanonicalAbi;
use NetFusion\Core\Wasm\Component\Exceptions\ComponentException;
use NetFusion\Core\Wasm\Component\Wit\Ast\WitFunction;
use NetFusion\Core\Wasm\Runtime\WasmInstance;
use NetFusion\Core\Wasm\Vm\LinearMemory;

/**
 * Instantiated Component with Canonical ABI lift/lower wrappers.
 */
class ComponentInstance
{
    private CanonicalAbi $abi;
    /** @var array<string, callable> */
    private array $instantiatedExports = [];
    private ?LinearMemory $memory = null;

    /**
     * @param ComponentModule $module
     * @param array<string, WasmInstance> $coreInstances
     * @param array<string, callable> $boundImports
     */
    public function __construct(
        private ComponentModule $module,
        private array $coreInstances = [],
        private array $boundImports = []
    ) {
        $this->abi = new CanonicalAbi();
        $this->initMemory();
        $this->bindExports();
    }

    public function getModule(): ComponentModule
    {
        return $this->module;
    }

    public function getMemory(): ?LinearMemory
    {
        return $this->memory;
    }

    /**
     * Call an exported component function.
     * Arguments and return values are marshalled through the Canonical ABI.
     */
    public function call(string $exportName, array $args = []): mixed
    {
        if (!isset($this->instantiatedExports[$exportName])) {
            throw new ComponentException("Export '{$exportName}' not found in component instance '{$this->module->getName()}'.");
        }

        return ($this->instantiatedExports[$exportName])(...$args);
    }

    public function getExport(string $exportName): ?callable
    {
        return $this->instantiatedExports[$exportName] ?? null;
    }

    /**
     * @return array<string, callable>
     */
    public function getExports(): array
    {
        return $this->instantiatedExports;
    }

    private function initMemory(): void
    {
        foreach ($this->coreInstances as $instance) {
            $mem = $instance->getMemory();
            if ($mem !== null) {
                $this->memory = $mem;
                break;
            }
        }
        if ($this->memory === null) {
            $this->memory = new LinearMemory(2, 10);
        }
    }

    private function bindExports(): void
    {
        foreach ($this->module->getExports() as $name => $export) {
            $target = $export->getTarget();

            if (is_callable($target)) {
                $this->instantiatedExports[$name] = $target;
                continue;
            }

            if ($target instanceof WitFunction) {
                $this->instantiatedExports[$name] = function (...$args) use ($target, $name) {
                    $paramTypes = [];
                    foreach ($target->getParams() as $pName => $pType) {
                        $paramTypes[$pName] = is_string($pType) ? $pType : $pType->getTypeName();
                    }

                    // Lower high-level args to flat Wasm args
                    $flatArgs = $this->abi->lower($args, $paramTypes, $this->memory);

                    // Execute core module function if available
                    $coreRes = 0;
                    $foundCore = false;
                    foreach ($this->coreInstances as $inst) {
                        $exports = $inst->getExports();
                        if (array_key_exists($name, $exports)) {
                            $coreRes = $inst->call($name, $flatArgs);
                            $foundCore = true;
                            break;
                        }
                    }

                    if (!$foundCore) {
                        // Mock computation or return flat representation
                        $coreRes = $flatArgs[0] ?? 0;
                    }

                    $flatReturn = is_array($coreRes) ? $coreRes : [$coreRes];
                    return $this->abi->lift($flatReturn, $target->getReturnTypeName(), $this->memory);
                };
            }
        }
    }
}
