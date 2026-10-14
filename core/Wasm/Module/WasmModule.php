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

namespace NetFusion\Core\Wasm\Module;

use NetFusion\Core\Wasm\Types\FunctionType;

/**
 * Validated in-memory representation of a WebAssembly 1.0 module.
 */
class WasmModule
{
    /**
     * @param int $version
     * @param array<FunctionType> $types
     * @param array<WasmImport> $imports
     * @param array<WasmFunction> $functions
     * @param array<array{initial: int, maximum: ?int}> $memories
     * @param array<array{type: int, initial: int, maximum: ?int}> $tables
     * @param array<array{type: \NetFusion\Core\Wasm\Types\ValueType, mutable: bool, initValue: mixed}> $globals
     * @param array<string, WasmExport> $exports
     * @param int|null $startFunction
     * @param array<array{index: int, offset: int, data: string}> $dataSegments
     * @param array<string, string> $customSections
     */
    public function __construct(
        public readonly int $version = 1,
        public readonly array $types = [],
        public readonly array $imports = [],
        public readonly array $functions = [],
        public readonly array $memories = [],
        public readonly array $tables = [],
        public readonly array $globals = [],
        public readonly array $exports = [],
        public readonly ?int $startFunction = null,
        public readonly array $dataSegments = [],
        public readonly array $customSections = []
    ) {
    }

    public function getExport(string $name): ?WasmExport
    {
        return $this->exports[$name] ?? null;
    }

    public function hasExport(string $name): bool
    {
        return isset($this->exports[$name]);
    }

    /**
     * Get count of imported functions (they occupy function indices 0 .. importedCount - 1).
     */
    public function getImportedFunctionCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp->isFunction()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Resolve a function index to its FunctionType signature.
     */
    public function getFunctionType(int $funcIndex): ?FunctionType
    {
        $importedFuncs = array_values(array_filter($this->imports, fn(WasmImport $i) => $i->isFunction()));
        $importedCount = count($importedFuncs);

        if ($funcIndex < $importedCount) {
            $typeIdx = $importedFuncs[$funcIndex]->getTypeIndex();
            return $this->types[$typeIdx] ?? null;
        }

        $localIndex = $funcIndex - $importedCount;
        if (isset($this->functions[$localIndex])) {
            $typeIdx = $this->functions[$localIndex]->typeIndex;
            return $this->types[$typeIdx] ?? null;
        }

        return null;
    }

    /**
     * Resolve a function index to its local WasmFunction implementation (returns null for imports).
     */
    public function getFunction(int $funcIndex): ?WasmFunction
    {
        $importedCount = $this->getImportedFunctionCount();
        if ($funcIndex < $importedCount) {
            return null; // Imported host function
        }

        $localIndex = $funcIndex - $importedCount;
        return $this->functions[$localIndex] ?? null;
    }

    /**
     * Find exported function by public export name.
     */
    public function findExportedFunction(string $exportName): ?array
    {
        $export = $this->getExport($exportName);
        if ($export === null || !$export->isFunction()) {
            return null;
        }

        $funcIndex = $export->index;
        return [
            'index' => $funcIndex,
            'type' => $this->getFunctionType($funcIndex),
            'function' => $this->getFunction($funcIndex),
        ];
    }
}
