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

/**
 * 64-bit eBPF Machine Instruction.
 * Standard layout: opcode (8-bit), dst_reg (4-bit), src_reg (4-bit), offset (16-bit), imm (32/64-bit).
 */
class Instruction
{
    private OpCode $op;
    private Register $dst;
    private Register $src;
    private int $offset;
    private int $imm;

    public function __construct(
        OpCode $op,
        Register $dst = Register::R0,
        Register $src = Register::R0,
        int $offset = 0,
        int $imm = 0
    ) {
        $this->op     = $op;
        $this->dst    = $dst;
        $this->src    = $src;
        $this->offset = $offset;
        $this->imm    = $imm;
    }

    public static function movImm(Register $dst, int $imm): self
    {
        return new self(OpCode::MOV_IMM, dst: $dst, imm: $imm);
    }

    public static function movReg(Register $dst, Register $src): self
    {
        return new self(OpCode::MOV_REG, dst: $dst, src: $src);
    }

    public static function addImm(Register $dst, int $imm): self
    {
        return new self(OpCode::ADD_IMM, dst: $dst, imm: $imm);
    }

    public static function addReg(Register $dst, Register $src): self
    {
        return new self(OpCode::ADD_REG, dst: $dst, src: $src);
    }

    public static function subImm(Register $dst, int $imm): self
    {
        return new self(OpCode::SUB_IMM, dst: $dst, imm: $imm);
    }

    public static function subReg(Register $dst, Register $src): self
    {
        return new self(OpCode::SUB_REG, dst: $dst, src: $src);
    }

    public static function mulImm(Register $dst, int $imm): self
    {
        return new self(OpCode::MUL_IMM, dst: $dst, imm: $imm);
    }

    public static function divImm(Register $dst, int $imm): self
    {
        return new self(OpCode::DIV_IMM, dst: $dst, imm: $imm);
    }

    public static function andImm(Register $dst, int $imm): self
    {
        return new self(OpCode::AND_IMM, dst: $dst, imm: $imm);
    }

    public static function orImm(Register $dst, int $imm): self
    {
        return new self(OpCode::OR_IMM, dst: $dst, imm: $imm);
    }

    public static function ldxW(Register $dst, Register $src, int $offset): self
    {
        return new self(OpCode::LDX_W, dst: $dst, src: $src, offset: $offset);
    }

    public static function stxW(Register $dst, Register $src, int $offset): self
    {
        return new self(OpCode::STX_W, dst: $dst, src: $src, offset: $offset);
    }

    public static function ja(int $offset): self
    {
        return new self(OpCode::JMP_JA, offset: $offset);
    }

    public static function jeq(Register $dst, int $imm, int $offset): self
    {
        return new self(OpCode::JMP_JEQ, dst: $dst, offset: $offset, imm: $imm);
    }

    public static function jne(Register $dst, int $imm, int $offset): self
    {
        return new self(OpCode::JMP_JNE, dst: $dst, offset: $offset, imm: $imm);
    }

    public static function jgt(Register $dst, int $imm, int $offset): self
    {
        return new self(OpCode::JMP_JGT, dst: $dst, offset: $offset, imm: $imm);
    }

    public static function jlt(Register $dst, int $imm, int $offset): self
    {
        return new self(OpCode::JMP_JLT, dst: $dst, offset: $offset, imm: $imm);
    }

    public static function call(int $funcId): self
    {
        return new self(OpCode::CALL, imm: $funcId);
    }

    public static function exit(): self
    {
        return new self(OpCode::EXIT);
    }

    public function getOp(): OpCode
    {
        return $this->op;
    }

    public function getDst(): Register
    {
        return $this->dst;
    }

    public function getSrc(): Register
    {
        return $this->src;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getImm(): int
    {
        return $this->imm;
    }

    public function isJump(): bool
    {
        return str_starts_with($this->op->value, 'J');
    }

    public function __toString(): string
    {
        return sprintf(
            "%s r%d, r%d, +%d, 0x%x",
            $this->op->value,
            $this->dst->value,
            $this->src->value,
            $this->offset,
            $this->imm
        );
    }
}
