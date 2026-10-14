<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Model/ComponentModule.php
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

use NetFusion\Core\Wasm\Component\Wit\Ast\WitWorld;
use NetFusion\Core\Wasm\Module\WasmModule;

/**
 * WebAssembly Component Model Container.
 * Packages core Wasm bytecode modules, WIT types, imports, and exports.
 */
class ComponentModule
{
    /** @var array<string, WasmModule> */
    private array $coreModules = [];
    /** @var array<string, ComponentImport> */
    private array $imports = [];
    /** @var array<string, ComponentExport> */
    private array $exports = [];
    private ?WitWorld $world = null;

    public function __construct(
        private string $name,
        private string $version = '0.1.0'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setWorld(WitWorld $world): self
    {
        $this->world = $world;
        return $this;
    }

    public function getWorld(): ?WitWorld
    {
        return $this->world;
    }

    public function addCoreModule(string $alias, WasmModule $module): self
    {
        $this->coreModules[$alias] = $module;
        return $this;
    }

    public function getCoreModule(string $alias): ?WasmModule
    {
        return $this->coreModules[$alias] ?? null;
    }

    /**
     * @return array<string, WasmModule>
     */
    public function getCoreModules(): array
    {
        return $this->coreModules;
    }

    public function addImport(ComponentImport $import): self
    {
        $this->imports[$import->getName()] = $import;
        return $this;
    }

    public function getImport(string $name): ?ComponentImport
    {
        return $this->imports[$name] ?? null;
    }

    /**
     * @return array<string, ComponentImport>
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    public function addExport(ComponentExport $export): self
    {
        $this->exports[$export->getName()] = $export;
        return $this;
    }

    public function getExport(string $name): ?ComponentExport
    {
        return $this->exports[$name] ?? null;
    }

    /**
     * @return array<string, ComponentExport>
     */
    public function getExports(): array
    {
        return $this->exports;
    }
}
