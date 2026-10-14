<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Network\Ebpf\Vm;

use NetFusion\Core\Network\Ebpf\Enums\OpCode;
use NetFusion\Core\Network\Ebpf\Enums\Register;
use NetFusion\Core\Network\Ebpf\Exceptions\ExecutionException;
use NetFusion\Core\Network\Ebpf\Maps\MapInterface;

/**
 * 64-bit eBPF Virtual Machine Execution Engine.
 * Simulates register bank (R0-R10), 512-byte stack frame, and kernel helper call dispatcher.
 */
class EbpfVm
{
    /** @var int[] 11 64-bit registers (R0 - R10) */
    private array $registers;
    /** @var array<int, int> 512-byte stack frame byte array */
    private array $stack;
    /** @var array<int, callable> helperId => callback */
    private array $helpers = [];
    /** @var array<int, MapInterface> mapId => Map */
    private array $maps = [];
    private int $instructionCount = 0;
    public const MAX_EXECUTION_STEPS = 10000;

    public function __construct()
    {
        $this->reset();
        $this->registerDefaultHelpers();
    }

    public function registerMap(int $mapId, MapInterface $map): void
    {
        $this->maps[$mapId] = $map;
    }

    public function registerHelper(int $helperId, callable $handler): void
    {
        $this->helpers[$helperId] = $handler;
    }

    public function reset(): void
    {
        $this->registers = array_fill(0, 11, 0);
        $this->stack = array_fill(0, 512, 0);
        // R10 is the frame pointer pointing to top of stack
        $this->registers[Register::R10->value] = 512;
        $this->instructionCount = 0;
    }

    /**
     * Execute an eBPF program with a given context pointer passed in R1.
     *
     * @param mixed $context Any context structure (e.g. XdpContext)
     * @return int Return value in R0 (e.g. XDP action code)
     */
    public function run(EbpfProgram $program, mixed $context = null): int
    {
        $r1Val = $this->registers[Register::R1->value];
        $this->reset();
        
        $ctxPointer = is_array($context) ? $context : (is_object($context) ? (array)$context : []);
        if (is_int($context)) {
            $this->registers[Register::R1->value] = $context;
        } elseif ($r1Val !== 0 && empty($ctxPointer)) {
            $this->registers[Register::R1->value] = $r1Val;
        } else {
            $this->registers[Register::R1->value] = 0;
        }

        $instructions = $program->getInstructions();
        $pc = 0;
        $total = count($instructions);

        while ($pc < $total) {
            $this->instructionCount++;
            if ($this->instructionCount > self::MAX_EXECUTION_STEPS) {
                throw new ExecutionException("Instruction step limit exceeded ({$this->instructionCount})");
            }

            $ins = $instructions[$pc];
            $op = $ins->getOp();
            $dst = $ins->getDst()->value;
            $src = $ins->getSrc()->value;
            $imm = $ins->getImm();
            $off = $ins->getOffset();

            switch ($op) {
                case OpCode::MOV_IMM:
                    $this->registers[$dst] = $imm;
                    break;
                case OpCode::MOV_REG:
                    $this->registers[$dst] = $this->registers[$src];
                    break;
                case OpCode::ADD_IMM:
                    $this->registers[$dst] = (int)($this->registers[$dst] + $imm);
                    break;
                case OpCode::ADD_REG:
                    $this->registers[$dst] = (int)($this->registers[$dst] + $this->registers[$src]);
                    break;
                case OpCode::SUB_IMM:
                    $this->registers[$dst] = (int)($this->registers[$dst] - $imm);
                    break;
                case OpCode::SUB_REG:
                    $this->registers[$dst] = (int)($this->registers[$dst] - $this->registers[$src]);
                    break;
                case OpCode::MUL_IMM:
                    $this->registers[$dst] = (int)($this->registers[$dst] * $imm);
                    break;
                case OpCode::DIV_IMM:
                    if ($imm === 0) {
                        throw new ExecutionException("Division by zero in eBPF VM at PC {$pc}");
                    }
                    $this->registers[$dst] = intdiv($this->registers[$dst], $imm);
                    break;
                case OpCode::AND_IMM:
                    $this->registers[$dst] &= $imm;
                    break;
                case OpCode::OR_IMM:
                    $this->registers[$dst] |= $imm;
                    break;
                case OpCode::XOR_IMM:
                    $this->registers[$dst] ^= $imm;
                    break;

                // Stack and memory stores/loads
                case OpCode::STX_W:
                    $targetAddr = $this->registers[$dst] + $off;
                    if ($targetAddr < 0 || $targetAddr >= 512) {
                        throw new ExecutionException("Out-of-bounds stack write at address {$targetAddr}");
                    }
                    $this->stack[$targetAddr] = $this->registers[$src] & 0xFFFFFFFF;
                    break;

                case OpCode::LDX_W:
                    $targetAddr = $this->registers[$src] + $off;
                    // If reading from context
                    if ($src === Register::R1->value && isset($ctxPointer[$off])) {
                        $this->registers[$dst] = (int)$ctxPointer[$off];
                    } elseif ($targetAddr >= 0 && $targetAddr < 512) {
                        $this->registers[$dst] = $this->stack[$targetAddr] ?? 0;
                    } else {
                        $this->registers[$dst] = 0;
                    }
                    break;

                // Branching
                case OpCode::JMP_JA:
                    $pc += $off;
                    break;
                case OpCode::JMP_JEQ:
                    if ($this->registers[$dst] === $imm) {
                        $pc += $off;
                    }
                    break;
                case OpCode::JMP_JNE:
                    if ($this->registers[$dst] !== $imm) {
                        $pc += $off;
                    }
                    break;
                case OpCode::JMP_JGT:
                    if ($this->registers[$dst] > $imm) {
                        $pc += $off;
                    }
                    break;
                case OpCode::JMP_JLT:
                    if ($this->registers[$dst] < $imm) {
                        $pc += $off;
                    }
                    break;

                // Helper Call
                case OpCode::CALL:
                    $funcId = $imm;
                    if (!isset($this->helpers[$funcId])) {
                        throw new ExecutionException("Unknown kernel helper function ID {$funcId}");
                    }
                    $ret = ($this->helpers[$funcId])(
                        $this->registers[Register::R1->value],
                        $this->registers[Register::R2->value],
                        $this->registers[Register::R3->value],
                        $this->registers[Register::R4->value],
                        $this->registers[Register::R5->value],
                        $this
                    );
                    $this->registers[Register::R0->value] = (int)$ret;
                    break;

                case OpCode::EXIT:
                    return $this->registers[Register::R0->value];
            }

            $pc++;
        }

        return $this->registers[Register::R0->value];
    }

    public function getRegister(Register $reg): int
    {
        return $this->registers[$reg->value];
    }

    public function setRegister(Register $reg, int $val): void
    {
        $this->registers[$reg->value] = $val;
    }

    public function getMap(int $mapId): ?MapInterface
    {
        return $this->maps[$mapId] ?? null;
    }

    private function registerDefaultHelpers(): void
    {
        // Helper 1: bpf_map_lookup_elem(mapId, key)
        $this->helpers[1] = function (int $mapId, int $key) {
            $map = $this->getMap($mapId);
            return $map !== null ? (int)$map->get($key) : 0;
        };

        // Helper 2: bpf_map_update_elem(mapId, key, val)
        $this->helpers[2] = function (int $mapId, int $key, int $val) {
            $map = $this->getMap($mapId);
            if ($map !== null) {
                $map->set($key, $val);
                return 0;
            }
            return -1;
        };

        // Helper 3: bpf_ktime_get_ns()
        $this->helpers[3] = function () {
            return (int)(microtime(true) * 1000000000);
        };
    }
}
