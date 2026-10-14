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

namespace NetFusion\Core\Wasm\Vm;

use NetFusion\Core\Wasm\Binary\Leb128;
use NetFusion\Core\Wasm\Module\WasmFunction;
use NetFusion\Core\Wasm\Module\WasmImport;
use NetFusion\Core\Wasm\Module\WasmModule;
use NetFusion\Core\Wasm\Runtime\FuelMeter;
use NetFusion\Core\Wasm\Types\ValueType;

/**
 * Pure PHP Stack-Based WebAssembly 1.0 (MVP) Virtual Machine & Interpreter.
 */
class VirtualMachine
{
    private const MAX_CALL_STACK_DEPTH = 1024;

    private WasmModule $module;
    private ?LinearMemory $memory = null;
    /** @var array<int, mixed> */
    private array $globals = [];
    /** @var array<string, callable> */
    private array $hostFunctions = [];
    private FuelMeter $fuelMeter;

    /** @var array<mixed> */
    private array $stack = [];
    /** @var array<StackFrame> */
    private array $callStack = [];

    public function __construct(
        WasmModule $module,
        ?LinearMemory $memory = null,
        array $hostFunctions = [],
        ?FuelMeter $fuelMeter = null
    ) {
        $this->module = $module;
        $this->hostFunctions = $hostFunctions;
        $this->fuelMeter = $fuelMeter ?? new FuelMeter(1000000);

        // Initialize Memory
        if ($memory !== null) {
            $this->memory = $memory;
        } elseif (!empty($module->memories)) {
            $memDesc = $module->memories[0];
            $this->memory = new LinearMemory($memDesc['initial'], $memDesc['maximum']);
        }

        // Initialize Globals
        foreach ($module->globals as $idx => $g) {
            $this->globals[$idx] = $g['initValue'];
        }

        // Apply Data Segments to Memory
        if ($this->memory !== null) {
            foreach ($module->dataSegments as $seg) {
                $this->memory->writeBytes($seg['offset'], $seg['data']);
            }
        }

        // Execute Start Function if declared
        if ($module->startFunction !== null) {
            $this->callFunction($module->startFunction, []);
        }
    }

    public function getMemory(): ?LinearMemory
    {
        return $this->memory;
    }

    public function setMemory(LinearMemory $memory): self
    {
        $this->memory = $memory;
        return $this;
    }

    public function getFuelMeter(): FuelMeter
    {
        return $this->fuelMeter;
    }

    public function registerHostFunction(string $module, string $name, callable $handler): self
    {
        $this->hostFunctions["{$module}.{$name}"] = $handler;
        return $this;
    }

    /**
     * Invoke an exported function by name or integer function index.
     *
     * @param string|int $funcOrName
     * @param array<mixed> $args
     * @return mixed
     */
    public function invoke(string|int $funcOrName, array $args = []): mixed
    {
        $funcIndex = is_int($funcOrName) ? $funcOrName : null;

        if (is_string($funcOrName)) {
            $export = $this->module->getExport($funcOrName);
            if ($export === null || !$export->isFunction()) {
                throw new \InvalidArgumentException("Exported function '{$funcOrName}' was not found in module.");
            }
            $funcIndex = $export->index;
        }

        if ($funcIndex === null) {
            throw new \InvalidArgumentException("Invalid function specifier: " . var_export($funcOrName, true));
        }

        return $this->callFunction($funcIndex, $args);
    }

    /**
     * Call function by internal index (either imported host function or local bytecode function).
     */
    public function callFunction(int $funcIndex, array $args): mixed
    {
        if (count($this->callStack) >= self::MAX_CALL_STACK_DEPTH) {
            throw TrapException::callStackExhausted(self::MAX_CALL_STACK_DEPTH);
        }

        $importedFuncs = array_values(array_filter($this->module->imports, fn(WasmImport $i) => $i->isFunction()));
        $importedCount = count($importedFuncs);

        // 1. Host Function Call
        if ($funcIndex < $importedCount) {
            $import = $importedFuncs[$funcIndex];
            $key = "{$import->module}.{$import->name}";
            $handler = $this->hostFunctions[$key] ?? null;

            if ($handler === null) {
                throw new \RuntimeException("Unbound host function import '{$key}'.");
            }

            return $handler(...$args);
        }

        // 2. Local Wasm Bytecode Function
        $wasmFunc = $this->module->getFunction($funcIndex);
        if ($wasmFunc === null) {
            throw new \OutOfBoundsException("Function index {$funcIndex} not defined.");
        }

        $sig = $this->module->getFunctionType($funcIndex);
        $returnArity = $sig ? count($sig->returns) : 0;

        // Prepare locals: [args..., declared_locals_initialized_to_zero...]
        $locals = [];
        $paramCount = $sig ? count($sig->params) : count($args);
        for ($i = 0; $i < $paramCount; $i++) {
            $locals[$i] = $args[$i] ?? 0;
        }

        $localTypes = $wasmFunc->locals;
        $localOffset = $paramCount;
        foreach ($localTypes as $lType) {
            $locals[$localOffset++] = match ($lType) {
                ValueType::I32, ValueType::I64 => 0,
                ValueType::F32, ValueType::F64 => 0.0,
                default => 0,
            };
        }

        $frame = new StackFrame(
            funcIndex: $funcIndex,
            locals: $locals,
            returnArity: $returnArity,
            stackBase: count($this->stack)
        );

        $this->callStack[] = $frame;

        try {
            $this->executeBytecode($wasmFunc, $frame);
        } finally {
            array_pop($this->callStack);
        }

        if ($returnArity === 0) {
            return null;
        }

        if ($returnArity === 1) {
            return array_pop($this->stack);
        }

        // Multi-value return
        $results = [];
        for ($i = 0; $i < $returnArity; $i++) {
            $results[] = array_pop($this->stack);
        }
        return array_reverse($results);
    }

    /**
     * Interpret bytecode instructions within a function frame.
     */
    private function executeBytecode(WasmFunction $func, StackFrame $frame): void
    {
        $code = $func->bytecode;
        $len = strlen($code);
        $ip = 0;

        // Root block for function body
        $frame->pushBlock('func', $len, count($this->stack), $frame->returnArity);

        // Precompute branch/block pairings
        $blockPairs = $this->precomputeControlFlow($code);

        while ($ip < $len) {
            $this->fuelMeter->consume(1);
            $curOpcodeIp = $ip;
            $opcode = ord($code[$ip++]);

            switch ($opcode) {
                // ---------------- CONTROL FLOW ----------------
                case Opcode::UNREACHABLE:
                    throw TrapException::unreachable();

                case Opcode::NOP:
                    break;

                case Opcode::BLOCK:
                    $blockType = $this->readBlockType($code, $ip);
                    $endIp = $blockPairs[$curOpcodeIp]['end'] ?? $len;
                    $frame->pushBlock('block', $endIp, count($this->stack), $blockType === 0x40 ? 0 : 1);
                    break;

                case Opcode::LOOP:
                    $blockType = $this->readBlockType($code, $ip);
                    $loopHeaderIp = $ip; // branch to loop jumps back to start of loop instructions
                    $frame->pushBlock('loop', $loopHeaderIp, count($this->stack), $blockType === 0x40 ? 0 : 1);
                    break;

                case Opcode::IF:
                    $blockType = $this->readBlockType($code, $ip);
                    $condition = (int) array_pop($this->stack);
                    $endIp = $blockPairs[$curOpcodeIp]['end'] ?? $len;
                    $elseIp = $blockPairs[$curOpcodeIp]['else'] ?? null;

                    if ($condition !== 0) {
                        // Enter 'then' block
                        $frame->pushBlock('if', $endIp, count($this->stack), $blockType === 0x40 ? 0 : 1);
                    } else {
                        // Condition false: jump to 'else' if present, or 'end'
                        if ($elseIp !== null) {
                            $ip = $elseIp;
                            $frame->pushBlock('if', $endIp, count($this->stack), $blockType === 0x40 ? 0 : 1);
                        } else {
                            $ip = $endIp;
                        }
                    }
                    break;

                case Opcode::ELSE:
                    $currentBlock = $frame->getBlock(0);
                    if ($currentBlock !== null && $currentBlock['type'] === 'if') {
                        // Successfully completed 'then' branch, jump to end of if block
                        $ip = $currentBlock['targetIp'];
                    }
                    break;

                case Opcode::END:
                    $poppedBlock = $frame->popBlock();
                    if ($poppedBlock === null || $poppedBlock['type'] === 'func') {
                        // Function completed
                        return;
                    }
                    break;

                case Opcode::BR:
                    $depth = Leb128::decodeUint($code, $ip);
                    $targetBlock = $frame->getBlock($depth);
                    if ($targetBlock === null) {
                        return;
                    }

                    $this->unwindStackToBlock($targetBlock);
                    $ip = $targetBlock['targetIp'];
                    // Pop blocks up to depth
                    for ($d = 0; $d <= $depth; $d++) {
                        $frame->popBlock();
                    }
                    break;

                case Opcode::BR_IF:
                    $depth = Leb128::decodeUint($code, $ip);
                    $condition = (int) array_pop($this->stack);
                    if ($condition !== 0) {
                        $targetBlock = $frame->getBlock($depth);
                        if ($targetBlock !== null) {
                            $this->unwindStackToBlock($targetBlock);
                            $ip = $targetBlock['targetIp'];
                            if ($targetBlock['type'] !== 'loop') {
                                for ($d = 0; $d <= $depth; $d++) {
                                    $frame->popBlock();
                                }
                            }
                        }
                    }
                    break;

                case Opcode::RETURN:
                    return;

                case Opcode::CALL:
                    $targetFuncIdx = Leb128::decodeUint($code, $ip);
                    $sig = $this->module->getFunctionType($targetFuncIdx);
                    $argCount = $sig ? count($sig->params) : 0;
                    $callArgs = [];
                    for ($a = 0; $a < $argCount; $a++) {
                        $callArgs[] = array_pop($this->stack);
                    }
                    $callArgs = array_reverse($callArgs);

                    $ret = $this->callFunction($targetFuncIdx, $callArgs);
                    if ($ret !== null) {
                        if (is_array($ret) && ($sig && count($sig->returns) > 1)) {
                            foreach ($ret as $v) {
                                $this->stack[] = $v;
                            }
                        } else {
                            $this->stack[] = $ret;
                        }
                    }
                    break;

                // ---------------- PARAMETRIC ----------------
                case Opcode::DROP:
                    array_pop($this->stack);
                    break;

                case Opcode::SELECT:
                    $cond = (int) array_pop($this->stack);
                    $val2 = array_pop($this->stack);
                    $val1 = array_pop($this->stack);
                    $this->stack[] = ($cond !== 0) ? $val1 : $val2;
                    break;

                // ---------------- VARIABLES ----------------
                case Opcode::LOCAL_GET:
                    $idx = Leb128::decodeUint($code, $ip);
                    $this->stack[] = $frame->getLocal($idx);
                    break;

                case Opcode::LOCAL_SET:
                    $idx = Leb128::decodeUint($code, $ip);
                    $frame->setLocal($idx, array_pop($this->stack));
                    break;

                case Opcode::LOCAL_TEE:
                    $idx = Leb128::decodeUint($code, $ip);
                    $val = end($this->stack);
                    $frame->setLocal($idx, $val);
                    break;

                case Opcode::GLOBAL_GET:
                    $idx = Leb128::decodeUint($code, $ip);
                    $this->stack[] = $this->globals[$idx] ?? 0;
                    break;

                case Opcode::GLOBAL_SET:
                    $idx = Leb128::decodeUint($code, $ip);
                    $this->globals[$idx] = array_pop($this->stack);
                    break;

                // ---------------- CONSTANTS ----------------
                case Opcode::I32_CONST:
                    $this->stack[] = (int) Leb128::decodeInt($code, $ip, 32);
                    break;

                case Opcode::I64_CONST:
                    $this->stack[] = (int) Leb128::decodeInt($code, $ip, 64);
                    break;

                case Opcode::F32_CONST:
                    $bytes = substr($code, $ip, 4);
                    $ip += 4;
                    $this->stack[] = (float) unpack('g', $bytes)[1];
                    break;

                case Opcode::F64_CONST:
                    $bytes = substr($code, $ip, 8);
                    $ip += 8;
                    $this->stack[] = (float) unpack('e', $bytes)[1];
                    break;

                // ---------------- COMPARISONS (i32) ----------------
                case Opcode::I32_EQZ:
                    $v = (int) array_pop($this->stack);
                    $this->stack[] = ($v === 0) ? 1 : 0;
                    break;

                case Opcode::I32_EQ:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ($a === $b) ? 1 : 0;
                    break;

                case Opcode::I32_NE:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ($a !== $b) ? 1 : 0;
                    break;

                case Opcode::I32_LT_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ($a < $b) ? 1 : 0;
                    break;

                case Opcode::I32_LT_U:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ((int) sprintf('%u', $a) < (int) sprintf('%u', $b)) ? 1 : 0;
                    break;

                case Opcode::I32_GT_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ($a > $b) ? 1 : 0;
                    break;

                case Opcode::I32_GT_U:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ((int) sprintf('%u', $a) > (int) sprintf('%u', $b)) ? 1 : 0;
                    break;

                case Opcode::I32_LE_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ($a <= $b) ? 1 : 0;
                    break;

                case Opcode::I32_LE_U:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ((int) sprintf('%u', $a) <= (int) sprintf('%u', $b)) ? 1 : 0;
                    break;

                case Opcode::I32_GE_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ($a >= $b) ? 1 : 0;
                    break;

                case Opcode::I32_GE_U:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = ((int) sprintf('%u', $a) >= (int) sprintf('%u', $b)) ? 1 : 0;
                    break;

                // ---------------- ARITHMETIC & BITWISE (i32) ----------------
                case Opcode::I32_ADD:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = (int) ($a + $b);
                    break;

                case Opcode::I32_SUB:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = (int) ($a - $b);
                    break;

                case Opcode::I32_MUL:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = (int) ($a * $b);
                    break;

                case Opcode::I32_DIV_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    if ($b === 0) {
                        throw TrapException::integerDivideByZero();
                    }
                    if ($a === -2147483648 && $b === -1) {
                        throw TrapException::integerOverflow();
                    }
                    $this->stack[] = intdiv($a, $b);
                    break;

                case Opcode::I32_DIV_U:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $uB = (int) sprintf('%u', $b);
                    $uA = (int) sprintf('%u', $a);
                    if ($uB === 0) {
                        throw TrapException::integerDivideByZero();
                    }
                    $this->stack[] = intdiv($uA, $uB);
                    break;

                case Opcode::I32_REM_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    if ($b === 0) {
                        throw TrapException::integerDivideByZero();
                    }
                    $this->stack[] = (int) ($a % $b);
                    break;

                case Opcode::I32_AND:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = $a & $b;
                    break;

                case Opcode::I32_OR:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = $a | $b;
                    break;

                case Opcode::I32_XOR:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = $a ^ $b;
                    break;

                case Opcode::I32_SHL:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = $a << ($b & 0x1F);
                    break;

                case Opcode::I32_SHR_S:
                    $b = (int) array_pop($this->stack);
                    $a = (int) array_pop($this->stack);
                    $this->stack[] = $a >> ($b & 0x1F);
                    break;

                case Opcode::I32_CLZ:
                    $a = (int) array_pop($this->stack);
                    $u = (int) sprintf('%u', $a);
                    $this->stack[] = ($u === 0) ? 32 : (32 - strlen(decbin($u)));
                    break;

                case Opcode::I32_POPCNT:
                    $a = (int) array_pop($this->stack);
                    $u = (int) sprintf('%u', $a);
                    $this->stack[] = substr_count(decbin($u), '1');
                    break;

                // ---------------- MEMORY ----------------
                case Opcode::I32_LOAD:
                    $align = Leb128::decodeUint($code, $ip);
                    $offset = Leb128::decodeUint($code, $ip);
                    $base = (int) array_pop($this->stack);
                    if ($this->memory === null) {
                        throw new \RuntimeException("Module has no active linear memory.");
                    }
                    $this->stack[] = $this->memory->readI32($base + $offset);
                    break;

                case Opcode::I32_LOAD8_U:
                    $align = Leb128::decodeUint($code, $ip);
                    $offset = Leb128::decodeUint($code, $ip);
                    $base = (int) array_pop($this->stack);
                    if ($this->memory === null) {
                        throw new \RuntimeException("Module has no active linear memory.");
                    }
                    $this->stack[] = $this->memory->readByte($base + $offset);
                    break;

                case Opcode::I32_STORE:
                    $align = Leb128::decodeUint($code, $ip);
                    $offset = Leb128::decodeUint($code, $ip);
                    $val = (int) array_pop($this->stack);
                    $base = (int) array_pop($this->stack);
                    if ($this->memory === null) {
                        throw new \RuntimeException("Module has no active linear memory.");
                    }
                    $this->memory->writeI32($base + $offset, $val);
                    break;

                case Opcode::I32_STORE8:
                    $align = Leb128::decodeUint($code, $ip);
                    $offset = Leb128::decodeUint($code, $ip);
                    $val = (int) array_pop($this->stack);
                    $base = (int) array_pop($this->stack);
                    if ($this->memory === null) {
                        throw new \RuntimeException("Module has no active linear memory.");
                    }
                    $this->memory->writeByte($base + $offset, $val);
                    break;

                case Opcode::MEMORY_SIZE:
                    $reserved = ord($code[$ip++]); // 0x00 reserved memory index
                    $this->stack[] = $this->memory ? $this->memory->getPageCount() : 0;
                    break;

                case Opcode::MEMORY_GROW:
                    $reserved = ord($code[$ip++]); // 0x00 reserved memory index
                    $delta = (int) array_pop($this->stack);
                    $this->stack[] = $this->memory ? $this->memory->grow($delta) : -1;
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf(
                        'Unsupported Wasm opcode 0x%02X (%s) at bytecode offset %d.',
                        $opcode,
                        Opcode::getName($opcode),
                        $ip - 1
                    ));
            }
        }
    }

    private function readBlockType(string $code, int &$ip): int
    {
        $b = ord($code[$ip++]);
        return $b;
    }

    private function unwindStackToBlock(array $block): void
    {
        $arity = $block['arity'] ?? 0;
        $results = [];
        for ($i = 0; $i < $arity; $i++) {
            if (!empty($this->stack)) {
                $results[] = array_pop($this->stack);
            }
        }

        // Unwind to block's recorded stack height
        while (count($this->stack) > $block['stackHeight']) {
            array_pop($this->stack);
        }

        // Push results back on stack
        foreach (array_reverse($results) as $r) {
            $this->stack[] = $r;
        }
    }

    /**
     * Precompute matching control flow blocks (block/loop/if -> else -> end).
     */
    private function precomputeControlFlow(string $code): array
    {
        $pairs = [];
        $stack = [];
        $len = strlen($code);
        $ip = 0;

        while ($ip < $len) {
            $curIp = $ip;
            $opcode = ord($code[$ip++]);

            if ($opcode === Opcode::BLOCK || $opcode === Opcode::LOOP || $opcode === Opcode::IF) {
                // Skip blocktype (1 byte or LEB128 type idx)
                $b = ord($code[$ip++]);
                if (($b & 0x80) !== 0) {
                    Leb128::decodeInt($code, $ip);
                }
                $stack[] = ['type' => $opcode, 'startIp' => $curIp, 'elseIp' => null];
            } elseif ($opcode === Opcode::ELSE) {
                if (!empty($stack)) {
                    $stack[count($stack) - 1]['elseIp'] = $ip;
                }
            } elseif ($opcode === Opcode::END) {
                if (!empty($stack)) {
                    $top = array_pop($stack);
                    $pairs[$top['startIp']] = [
                        'end' => $curIp,
                        'else' => $top['elseIp'],
                    ];
                }
            } elseif ($opcode === Opcode::BR || $opcode === Opcode::BR_IF) {
                Leb128::decodeUint($code, $ip);
            } elseif ($opcode === Opcode::CALL) {
                Leb128::decodeUint($code, $ip);
            } elseif ($opcode === Opcode::LOCAL_GET || $opcode === Opcode::LOCAL_SET || $opcode === Opcode::LOCAL_TEE ||
                      $opcode === Opcode::GLOBAL_GET || $opcode === Opcode::GLOBAL_SET) {
                Leb128::decodeUint($code, $ip);
            } elseif ($opcode === Opcode::I32_CONST) {
                Leb128::decodeInt($code, $ip, 32);
            } elseif ($opcode === Opcode::I64_CONST) {
                Leb128::decodeInt($code, $ip, 64);
            } elseif ($opcode === Opcode::F32_CONST) {
                $ip += 4;
            } elseif ($opcode === Opcode::F64_CONST) {
                $ip += 8;
            } elseif ($opcode === Opcode::I32_LOAD || $opcode === Opcode::I32_STORE ||
                      $opcode === Opcode::I32_LOAD8_U || $opcode === Opcode::I32_STORE8) {
                Leb128::decodeUint($code, $ip); // align
                Leb128::decodeUint($code, $ip); // offset
            } elseif ($opcode === Opcode::MEMORY_SIZE || $opcode === Opcode::MEMORY_GROW) {
                $ip++; // reserved byte
            }
        }

        return $pairs;
    }
}
