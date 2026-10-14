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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Network\Ebpf\EbpfManager;
use NetFusion\Core\Network\Ebpf\Enums\Register;
use NetFusion\Core\Network\Ebpf\Enums\XdpAction;

/**
 * eBPF Program Assembler and Verifier CLI Command.
 */
class EbpfLoadCommand extends Command
{
    protected string $name = 'ebpf:load';
    protected string $description = 'Load and statically verify an eBPF program into kernel VM';

    public function handle(): int
    {
        $progName = $this->argument(0, 'drop_syn_flood');
        $this->info("Assembling and verifying eBPF program: {$progName}...");

        $mgr = $this->app->has('ebpf') ? $this->app->get('ebpf') : new EbpfManager();
        $prog = $mgr->createProgram($progName);

        // Simple XDP filter: check SYN flag (offset 5 in ctx), if SYN => DROP, else PASS
        $prog->ldxW(Register::R0, Register::R1, 5) // R0 = ctx->is_syn
             ->jeq(Register::R0, 1, 2)            // if is_syn == 1 jump +2 to DROP
             ->movImm(Register::R0, XdpAction::XDP_PASS->value)
             ->exit()
             ->movImm(Register::R0, XdpAction::XDP_DROP->value)
             ->exit();

        $mgr->loadProgram($prog);
        $this->success("✓ Program {$progName} passed static verifier (CFG & DAG loop-free)!");
        $this->line($prog->disassemble());

        return 0;
    }
}
