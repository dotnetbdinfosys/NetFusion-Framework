<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Abi/CanonicalAbi.php
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

namespace NetFusion\Core\Wasm\Component\Abi;

use NetFusion\Core\Wasm\Component\Exceptions\CanonicalAbiException;
use NetFusion\Core\Wasm\Vm\LinearMemory;

/**
 * WebAssembly Component Model Canonical ABI Engine.
 * Handles flattening, lifting (memory -> high level value) and lowering (value -> memory/flat args).
 */
class CanonicalAbi
{
    /**
     * Lower high-level argument values into flat core Wasm primitives (i32, i64, f32, f64).
     *
     * @param array<string|int, mixed> $args High-level arguments
     * @param array<string|int, string> $paramTypes WIT types
     * @param LinearMemory|null $memory Linear memory for allocating strings/lists
     * @param callable|null $realloc Function to allocate memory in Wasm guest
     * @return array<int, int|float> Flattened core Wasm numbers
     */
    public function lower(
        array $args,
        array $paramTypes,
        ?LinearMemory $memory = null,
        ?callable $realloc = null
    ): array {
        $flat = [];
        $keys = array_keys($paramTypes);

        foreach ($keys as $idx => $key) {
            $val = $args[$key] ?? $args[$idx] ?? null;
            $type = $paramTypes[$key];

            $flatPart = $this->lowerValue($val, $type, $memory, $realloc);
            foreach ($flatPart as $f) {
                $flat[] = $f;
            }
        }

        return $flat;
    }

    /**
     * Lift flat core Wasm return values into high-level type.
     *
     * @param array<int, int|float> $flatValues
     * @param string|null $returnType
     * @param LinearMemory|null $memory
     * @return mixed
     */
    public function lift(
        array $flatValues,
        ?string $returnType,
        ?LinearMemory $memory = null
    ): mixed {
        if ($returnType === null || strtolower($returnType) === 'void') {
            return null;
        }

        $type = strtolower(trim($returnType));

        // Primitive scalars
        if (in_array($type, ['bool', 'u8', 'u16', 'u32', 's8', 's16', 's32', 'char'], true)) {
            $v = (int)($flatValues[0] ?? 0);
            return $type === 'bool' ? ($v !== 0) : $v;
        }

        if (in_array($type, ['u64', 's64'], true)) {
            return (int)($flatValues[0] ?? 0);
        }

        if (in_array($type, ['f32', 'f64'], true)) {
            return (float)($flatValues[0] ?? 0.0);
        }

        // String representation (ptr, len)
        if ($type === 'string') {
            $ptr = (int)($flatValues[0] ?? 0);
            $len = (int)($flatValues[1] ?? 0);
            if ($memory === null) {
                throw new CanonicalAbiException("LinearMemory required to lift string type");
            }
            return $memory->readBytes($ptr, $len);
        }

        // List representation (ptr, count)
        if (str_starts_with($type, 'list<')) {
            $ptr = (int)($flatValues[0] ?? 0);
            $count = (int)($flatValues[1] ?? 0);
            if ($memory === null) {
                return [];
            }
            $bytes = $memory->readBytes($ptr, $count);
            return array_values(unpack('C*', $bytes) ?: []);
        }

        return $flatValues[0] ?? null;
    }

    /**
     * Lower an individual high-level value into flat core representation.
     */
    private function lowerValue(
        mixed $val,
        string $type,
        ?LinearMemory $memory,
        ?callable $realloc
    ): array {
        $type = strtolower(trim($type));

        if ($type === 'bool') {
            return [(bool)$val ? 1 : 0];
        }

        if (in_array($type, ['u8', 'u16', 'u32', 's8', 's16', 's32', 'char'], true)) {
            return [(int)$val];
        }

        if (in_array($type, ['u64', 's64'], true)) {
            return [(int)$val];
        }

        if (in_array($type, ['f32', 'f64'], true)) {
            return [(float)$val];
        }

        if ($type === 'string') {
            $str = (string)$val;
            $len = strlen($str);
            $ptr = 1024; // Default scratch offset if realloc not provided

            if ($realloc !== null) {
                $ptr = $realloc(0, 0, 1, $len);
            }

            if ($memory !== null) {
                $memory->writeBytes($ptr, $str);
            }

            return [$ptr, $len];
        }

        // Default scalar fallback
        return [(int)$val];
    }
}
