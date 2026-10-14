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
use NetFusion\Core\Network\Ebpf\Xdp\Packet;

/**
 * XDP Network Interface Attachment CLI Command.
 */
class XdpAttachCommand extends Command
{
    protected string $name = 'xdp:attach';
    protected string $description = 'Attach an eBPF XDP filter to network interface driver';

    public function handle(): int
    {
        $iface = $this->argument(0, 'eth0');
        $this->info("Attaching XDP filter to network interface: {$iface}...");

        $mgr = $this->app->has('ebpf') ? $this->app->get('ebpf') : new EbpfManager();
        $prog = $mgr->createProgram('xdp_filter');
        $prog->movImm(Register::R0, XdpAction::XDP_PASS->value)->exit();

        $mgr->attachXdp($prog);

        // Simulate incoming test packet
        $packet = Packet::tcpSyn('192.168.1.50', '10.0.0.1', 44322, 80);
        $action = $mgr->filterPacket($packet);

        $this->success("✓ XDP filter active on {$iface} [Test packet result: {$action->name}]");
        return 0;
    }
}
