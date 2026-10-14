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
 * eBPF Virtual Machine 64-bit Registers (R0 - R10).
 */
enum Register: int
{
    case R0 = 0;   // Return value / XDP action code
    case R1 = 1;   // Context / Function arg 1
    case R2 = 2;   // Function arg 2
    case R3 = 3;   // Function arg 3
    case R4 = 4;   // Function arg 4
    case R5 = 5;   // Function arg 5
    case R6 = 6;   // Callee saved 1
    case R7 = 7;   // Callee saved 2
    case R8 = 8;   // Callee saved 3
    case R9 = 9;   // Callee saved 4
    case R10 = 10; // Read-only stack frame pointer
}
