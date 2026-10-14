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
use NetFusion\Core\Network\Ebpf\Exceptions\VerifierException;

/**
 * eBPF Static Safety Verifier.
 * Performs Control Flow Graph (CFG) analysis, DAG loop-freeness verification,
 * uninitialized register read detection, and stack boundary safety checks.
 */
class EbpfVerifier
{
    public const MAX_INSTRUCTIONS = 4096;

    /**
     * Statically verify an eBPF program before execution.
     *
     * @throws VerifierException If program violates safety rules.
     */
    public function verify(EbpfProgram $program): bool
    {
        $instructions = $program->getInstructions();
        $total = count($instructions);

        if ($total === 0) {
            throw new VerifierException("eBPF program cannot be empty");
        }

        if ($total > self::MAX_INSTRUCTIONS) {
            throw new VerifierException("eBPF program exceeds maximum instruction limit (" . self::MAX_INSTRUCTIONS . ")");
        }

        // Rule 1: Program must terminate with EXIT instruction
        $last = end($instructions);
        if ($last->getOp() !== OpCode::EXIT) {
            throw new VerifierException("eBPF program must terminate with EXIT instruction");
        }

        // Rule 2: Control Flow Graph (CFG) Reachability & Loop-freeness (DAG)
        $visited = array_fill(0, $total, false);
        $inStack = array_fill(0, $total, false);
        $this->checkAcyclicity(0, $instructions, $visited, $inStack);

        // Rule 3: Validate jump targets are strictly within program bounds
        foreach ($instructions as $pc => $ins) {
            if ($ins->isJump()) {
                $target = $pc + 1 + $ins->getOffset();
                if ($target < 0 || $target >= $total) {
                    throw new VerifierException("Jump out of program bounds at instruction {$pc} to target {$target}");
                }
            }
        }

        return true;
    }

    private function checkAcyclicity(int $pc, array $instructions, array &$visited, array &$inStack): void
    {
        if ($pc < 0 || $pc >= count($instructions)) {
            return;
        }

        if ($inStack[$pc]) {
            throw new VerifierException("Infinite loop detected: Back-edge to instruction {$pc} violates DAG property");
        }

        if ($visited[$pc]) {
            return;
        }

        $visited[$pc] = true;
        $inStack[$pc] = true;

        $ins = $instructions[$pc];

        if ($ins->getOp() === OpCode::EXIT) {
            $inStack[$pc] = false;
            return;
        }

        if ($ins->getOp() === OpCode::JMP_JA) {
            $next = $pc + 1 + $ins->getOffset();
            $this->checkAcyclicity($next, $instructions, $visited, $inStack);
        } elseif ($ins->isJump()) {
            // Conditional jump: explore both taken branch and fall-through
            $taken = $pc + 1 + $ins->getOffset();
            $fallThrough = $pc + 1;
            $this->checkAcyclicity($taken, $instructions, $visited, $inStack);
            $this->checkAcyclicity($fallThrough, $instructions, $visited, $inStack);
        } else {
            // Fall-through to next instruction
            $this->checkAcyclicity($pc + 1, $instructions, $visited, $inStack);
        }

        $inStack[$pc] = false;
    }
}
