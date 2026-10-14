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

namespace NetFusion\Core\Wasm\Binary;

use NetFusion\Core\Wasm\Module\WasmExport;
use NetFusion\Core\Wasm\Module\WasmFunction;
use NetFusion\Core\Wasm\Module\WasmImport;
use NetFusion\Core\Wasm\Module\WasmModule;
use NetFusion\Core\Wasm\Types\FunctionType;
use NetFusion\Core\Wasm\Types\SectionType;
use NetFusion\Core\Wasm\Types\ValueType;

/**
 * High-performance WebAssembly 1.0 Binary Module Parser and Validator.
 */
class ModuleParser
{
    private const WASM_MAGIC = "\x00\x61\x73\x6D"; // \0asm
    private const WASM_VERSION = 1;

    /**
     * Parse raw binary string into a validated WasmModule instance.
     */
    public function parse(string $binary): WasmModule
    {
        $reader = new BinaryReader($binary);

        // 1. Validate magic header
        $magic = $reader->readBytes(4);
        if ($magic !== self::WASM_MAGIC) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid WebAssembly magic header: expected "\\0asm" (0x0061736D), got "%s".',
                addcslashes($magic, "\0..\37")
            ));
        }

        // 2. Validate version
        $versionBytes = $reader->readBytes(4);
        $version = unpack('V', $versionBytes)[1]; // Little-endian 32-bit uint
        if ($version !== self::WASM_VERSION) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported WebAssembly binary version: %d (expected 1).',
                $version
            ));
        }

        $types = [];
        $imports = [];
        $functionTypeIndices = [];
        $memories = [];
        $tables = [];
        $globals = [];
        $exports = [];
        $startFunction = null;
        $codeBodies = [];
        $dataSegments = [];
        $customSections = [];

        // 3. Parse Sections
        while (!$reader->isEof()) {
            $sectionId = $reader->readByte();
            $sectionSize = $reader->readUint32();
            $sectionReader = $reader->slice($sectionSize);

            $sectionType = SectionType::tryFrom($sectionId);
            if ($sectionType === null) {
                continue; // Unknown or future section ID
            }

            switch ($sectionType) {
                case SectionType::CUSTOM:
                    $name = $sectionReader->readString();
                    $customSections[$name] = $sectionReader->readBytes($sectionReader->remaining());
                    break;

                case SectionType::TYPE:
                    $types = $this->parseTypeSection($sectionReader);
                    break;

                case SectionType::IMPORT:
                    $imports = $this->parseImportSection($sectionReader);
                    break;

                case SectionType::FUNCTION:
                    $functionTypeIndices = $this->parseFunctionSection($sectionReader);
                    break;

                case SectionType::TABLE:
                    $tables = $this->parseTableSection($sectionReader);
                    break;

                case SectionType::MEMORY:
                    $memories = $this->parseMemorySection($sectionReader);
                    break;

                case SectionType::GLOBAL:
                    $globals = $this->parseGlobalSection($sectionReader);
                    break;

                case SectionType::EXPORT:
                    $exports = $this->parseExportSection($sectionReader);
                    break;

                case SectionType::START:
                    $startFunction = $sectionReader->readUint32();
                    break;

                case SectionType::CODE:
                    $codeBodies = $this->parseCodeSection($sectionReader);
                    break;

                case SectionType::DATA:
                    $dataSegments = $this->parseDataSection($sectionReader);
                    break;

                default:
                    // Ignored sections (e.g. Element, DataCount)
                    break;
            }
        }

        // 4. Assemble WasmFunction instances by combining function signatures and code bodies
        $functions = [];
        $funcCount = count($functionTypeIndices);
        for ($i = 0; $i < $funcCount; $i++) {
            $typeIdx = $functionTypeIndices[$i];
            $body = $codeBodies[$i] ?? ['locals' => [], 'bytecode' => ''];
            $functions[] = new WasmFunction(
                typeIndex: $typeIdx,
                locals: $body['locals'],
                bytecode: $body['bytecode']
            );
        }

        return new WasmModule(
            version: $version,
            types: $types,
            imports: $imports,
            functions: $functions,
            memories: $memories,
            tables: $tables,
            globals: $globals,
            exports: $exports,
            startFunction: $startFunction,
            dataSegments: $dataSegments,
            customSections: $customSections
        );
    }

    private function parseTypeSection(BinaryReader $r): array
    {
        return $r->readVector(function (BinaryReader $br) {
            $form = $br->readByte();
            if ($form !== 0x60) { // Wasm func type indicator
                throw new \InvalidArgumentException(sprintf('Unexpected function type form 0x%02X (expected 0x60).', $form));
            }

            $params = $br->readVector(fn(BinaryReader $p) => $p->readValueType());
            $returns = $br->readVector(fn(BinaryReader $ret) => $ret->readValueType());

            return new FunctionType($params, $returns);
        });
    }

    private function parseImportSection(BinaryReader $r): array
    {
        return $r->readVector(function (BinaryReader $br) {
            $module = $br->readString();
            $field = $br->readString();
            $kind = $br->readByte();

            $descriptor = match ($kind) {
                WasmImport::KIND_FUNCTION => $br->readUint32(), // Type index
                WasmImport::KIND_TABLE => $this->parseLimits($br),
                WasmImport::KIND_MEMORY => $this->parseLimits($br),
                WasmImport::KIND_GLOBAL => [
                    'type' => $br->readValueType(),
                    'mutable' => $br->readByte() === 0x01,
                ],
                default => throw new \InvalidArgumentException("Unknown import kind: {$kind}"),
            };

            return new WasmImport($module, $field, $kind, $descriptor);
        });
    }

    private function parseFunctionSection(BinaryReader $r): array
    {
        return $r->readVector(fn(BinaryReader $br) => $br->readUint32());
    }

    private function parseTableSection(BinaryReader $r): array
    {
        return $r->readVector(function (BinaryReader $br) {
            $elemType = $br->readByte(); // 0x70 for funcref
            $limits = $this->parseLimits($br);
            return array_merge(['type' => $elemType], $limits);
        });
    }

    private function parseMemorySection(BinaryReader $r): array
    {
        return $r->readVector(fn(BinaryReader $br) => $this->parseLimits($br));
    }

    private function parseGlobalSection(BinaryReader $r): array
    {
        return $r->readVector(function (BinaryReader $br) {
            $type = $br->readValueType();
            $mutable = $br->readByte() === 0x01;
            $initValue = $this->parseInitExpr($br);
            return [
                'type' => $type,
                'mutable' => $mutable,
                'initValue' => $initValue,
            ];
        });
    }

    private function parseExportSection(BinaryReader $r): array
    {
        $exports = [];
        $list = $r->readVector(function (BinaryReader $br) {
            $name = $br->readString();
            $kind = $br->readByte();
            $index = $br->readUint32();
            return new WasmExport($name, $kind, $index);
        });

        foreach ($list as $exp) {
            $exports[$exp->name] = $exp;
        }

        return $exports;
    }

    private function parseCodeSection(BinaryReader $r): array
    {
        return $r->readVector(function (BinaryReader $br) {
            $bodySize = $br->readUint32();
            $bodyReader = $br->slice($bodySize);

            // Read local variable declarations: count of local declarations
            $locals = [];
            $localDeclCount = $bodyReader->readUint32();
            for ($i = 0; $i < $localDeclCount; $i++) {
                $count = $bodyReader->readUint32();
                $type = $bodyReader->readValueType();
                for ($c = 0; $c < $count; $c++) {
                    $locals[] = $type;
                }
            }

            // Remainder of body is bytecode ending with 0x0B (END)
            $bytecode = $bodyReader->readBytes($bodyReader->remaining());

            return [
                'locals' => $locals,
                'bytecode' => $bytecode,
            ];
        });
    }

    private function parseDataSection(BinaryReader $r): array
    {
        return $r->readVector(function (BinaryReader $br) {
            $flags = $br->readUint32();
            $memIndex = 0;
            $offset = 0;

            if ($flags === 0) {
                // Active segment on memory 0
                $offset = $this->parseInitExpr($br);
            } elseif ($flags === 2) {
                // Active segment on custom memory index
                $memIndex = $br->readUint32();
                $offset = $this->parseInitExpr($br);
            }

            $bytesLen = $br->readUint32();
            $data = $br->readBytes($bytesLen);

            return [
                'index' => $memIndex,
                'offset' => (int) $offset,
                'data' => $data,
            ];
        });
    }

    private function parseLimits(BinaryReader $r): array
    {
        $flags = $r->readByte();
        $initial = $r->readUint32();
        $maximum = null;
        if (($flags & 0x01) !== 0) {
            $maximum = $r->readUint32();
        }
        return ['initial' => $initial, 'maximum' => $maximum];
    }

    private function parseInitExpr(BinaryReader $r): mixed
    {
        $opcode = $r->readByte();
        $value = 0;

        switch ($opcode) {
            case 0x41: // i32.const
                $value = $r->readInt32();
                break;
            case 0x42: // i64.const
                $value = $r->readInt64();
                break;
            case 0x43: // f32.const
                $value = $r->readFloat32();
                break;
            case 0x44: // f64.const
                $value = $r->readFloat64();
                break;
            case 0x23: // global.get
                $value = $r->readUint32();
                break;
            default:
                break;
        }

        // Read 0x0B (END) terminating the constant expression
        $end = $r->readByte();
        if ($end !== 0x0B) {
            throw new \InvalidArgumentException(sprintf('Expected 0x0B (END) in constant expression, got 0x%02X.', $end));
        }

        return $value;
    }
}
