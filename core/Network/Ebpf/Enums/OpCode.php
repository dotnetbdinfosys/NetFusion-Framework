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

namespace NetFusion\Core\Network\Ebpf\Enums;

/**
 * eBPF ISA OpCode classification.
 */
enum OpCode: string
{
    // ALU 64-bit
    case ADD_IMM = 'ADD_IMM';
    case ADD_REG = 'ADD_REG';
    case SUB_IMM = 'SUB_IMM';
    case SUB_REG = 'SUB_REG';
    case MUL_IMM = 'MUL_IMM';
    case MUL_REG = 'MUL_REG';
    case DIV_IMM = 'DIV_IMM';
    case DIV_REG = 'DIV_REG';
    case AND_IMM = 'AND_IMM';
    case AND_REG = 'AND_REG';
    case OR_IMM  = 'OR_IMM';
    case OR_REG  = 'OR_REG';
    case XOR_IMM = 'XOR_IMM';
    case XOR_REG = 'XOR_REG';
    case MOV_IMM = 'MOV_IMM';
    case MOV_REG = 'MOV_REG';

    // Jumps
    case JMP_JA  = 'JA';      // Unconditional jump
    case JMP_JEQ = 'JEQ';     // Jump if equal
    case JMP_JNE = 'JNE';     // Jump if not equal
    case JMP_JGT = 'JGT';     // Jump if greater than
    case JMP_JGE = 'JGE';     // Jump if greater or equal
    case JMP_JLT = 'JLT';     // Jump if less than
    case JMP_JLE = 'JLE';     // Jump if less or equal

    // Memory (Load / Store)
    case LD_DW   = 'LD_DW';   // Load double word (64-bit imm)
    case LDX_W   = 'LDX_W';   // Load 32-bit word from memory [src + offset]
    case STX_W   = 'STX_W';   // Store 32-bit word into memory [dst + offset]
    case STX_DW  = 'STX_DW';  // Store 64-bit double word into memory

    // Kernel helper call & exit
    case CALL    = 'CALL';
    case EXIT    = 'EXIT';
}
