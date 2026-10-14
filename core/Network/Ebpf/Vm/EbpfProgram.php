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

use NetFusion\Core\Network\Ebpf\Enums\Register;

/**
 * Container and fluent bytecode builder for eBPF Programs.
 */
class EbpfProgram
{
    private string $name;
    /** @var Instruction[] */
    private array $instructions = [];

    public function __construct(string $name = 'main')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addInstruction(Instruction $ins): self
    {
        $this->instructions[] = $ins;
        return $this;
    }

    /**
     * @return Instruction[]
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    public function count(): int
    {
        return count($this->instructions);
    }

    // --- Fluent assembler instructions ---

    public function movImm(Register $dst, int $imm): self
    {
        return $this->addInstruction(Instruction::movImm($dst, $imm));
    }

    public function movReg(Register $dst, Register $src): self
    {
        return $this->addInstruction(Instruction::movReg($dst, $src));
    }

    public function addImm(Register $dst, int $imm): self
    {
        return $this->addInstruction(Instruction::addImm($dst, $imm));
    }

    public function subImm(Register $dst, int $imm): self
    {
        return $this->addInstruction(Instruction::subImm($dst, $imm));
    }

    public function ldxW(Register $dst, Register $src, int $offset): self
    {
        return $this->addInstruction(Instruction::ldxW($dst, $src, $offset));
    }

    public function stxW(Register $dst, Register $src, int $offset): self
    {
        return $this->addInstruction(Instruction::stxW($dst, $src, $offset));
    }

    public function jeq(Register $dst, int $imm, int $offset): self
    {
        return $this->addInstruction(Instruction::jeq($dst, $imm, $offset));
    }

    public function jne(Register $dst, int $imm, int $offset): self
    {
        return $this->addInstruction(Instruction::jne($dst, $imm, $offset));
    }

    public function jgt(Register $dst, int $imm, int $offset): self
    {
        return $this->addInstruction(Instruction::jgt($dst, $imm, $offset));
    }

    public function call(int $funcId): self
    {
        return $this->addInstruction(Instruction::call($funcId));
    }

    public function exit(): self
    {
        return $this->addInstruction(Instruction::exit());
    }

    public function disassemble(): string
    {
        $lines = ["# Program: {$this->name} (" . count($this->instructions) . " instructions)"];
        foreach ($this->instructions as $idx => $ins) {
            $lines[] = sprintf("%04d: %s", $idx, (string)$ins);
        }
        return implode("\n", $lines);
    }
}
