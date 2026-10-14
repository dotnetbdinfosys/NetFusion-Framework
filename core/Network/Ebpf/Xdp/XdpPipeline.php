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

namespace NetFusion\Core\Network\Ebpf\Xdp;

use NetFusion\Core\Network\Ebpf\Enums\XdpAction;
use NetFusion\Core\Network\Ebpf\Maps\ArrayMap;
use NetFusion\Core\Network\Ebpf\Maps\HashMap;
use NetFusion\Core\Network\Ebpf\Vm\EbpfProgram;
use NetFusion\Core\Network\Ebpf\Vm\EbpfVm;

/**
 * High-performance XDP (eXpress Data Path) Packet Processing Pipeline.
 * Executes programmable byte-level packet filters at wire speed.
 */
class XdpPipeline
{
    private EbpfVm $vm;
    private ?EbpfProgram $program = null;
    /** @var array<string, int> Action name => packet count */
    private array $actionCounters = [];
    private HashMap $blacklistMap;
    private ArrayMap $statsMap;

    public function __construct(?EbpfVm $vm = null)
    {
        $this->vm = $vm ?? new EbpfVm();
        $this->blacklistMap = new HashMap('xdp_blacklist', 10000);
        $this->statsMap = new ArrayMap('xdp_stats', 10);

        $this->vm->registerMap(1, $this->blacklistMap);
        $this->vm->registerMap(2, $this->statsMap);

        $this->resetCounters();
    }

    public function attachProgram(EbpfProgram $program): void
    {
        $this->program = $program;
    }

    public function blacklistIp(string $ip): void
    {
        $this->blacklistMap->set(ip2long($ip) ?: $ip, 1);
    }

    public function whitelistIp(string $ip): void
    {
        $this->blacklistMap->delete(ip2long($ip) ?: $ip);
    }

    public function isBlacklisted(string $ip): bool
    {
        return $this->blacklistMap->has(ip2long($ip) ?: $ip);
    }

    /**
     * Process an incoming packet through the attached XDP program.
     */
    public function processPacket(Packet $packet): XdpAction
    {
        // 1. Fast-path check blacklist
        if ($this->isBlacklisted($packet->getSrcIp())) {
            $this->actionCounters['XDP_DROP']++;
            $this->statsMap->increment(XdpAction::XDP_DROP->value);
            return XdpAction::XDP_DROP;
        }

        // 2. If program attached, run eBPF VM
        if ($this->program !== null) {
            $ctx = new XdpContext($packet);
            $rawRet = $this->vm->run($this->program, $ctx->toArray());
            $action = XdpAction::tryFrom($rawRet) ?? XdpAction::XDP_PASS;
        } else {
            $action = XdpAction::XDP_PASS;
        }

        $this->actionCounters[$action->name]++;
        $this->statsMap->increment($action->value);
        return $action;
    }

    public function getCounters(): array
    {
        return $this->actionCounters;
    }

    public function resetCounters(): void
    {
        $this->actionCounters = [
            'XDP_ABORTED'  => 0,
            'XDP_DROP'     => 0,
            'XDP_PASS'     => 0,
            'XDP_TX'       => 0,
            'XDP_REDIRECT' => 0,
        ];
        $this->statsMap->clear();
    }
}
