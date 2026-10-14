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

namespace NetFusion\Core\Network\Ebpf;

use NetFusion\Core\Network\Ebpf\Enums\Register;
use NetFusion\Core\Network\Ebpf\Enums\XdpAction;
use NetFusion\Core\Network\Ebpf\Maps\ArrayMap;
use NetFusion\Core\Network\Ebpf\Maps\HashMap;
use NetFusion\Core\Network\Ebpf\Maps\LruHashMap;
use NetFusion\Core\Network\Ebpf\Maps\MapInterface;
use NetFusion\Core\Network\Ebpf\Maps\RingBufferMap;
use NetFusion\Core\Network\Ebpf\Observability\FlowMonitor;
use NetFusion\Core\Network\Ebpf\Observability\HttpInspector;
use NetFusion\Core\Network\Ebpf\Vm\EbpfProgram;
use NetFusion\Core\Network\Ebpf\Vm\EbpfVerifier;
use NetFusion\Core\Network\Ebpf\Vm\EbpfVm;
use NetFusion\Core\Network\Ebpf\Xdp\Packet;
use NetFusion\Core\Network\Ebpf\Xdp\XdpPipeline;

/**
 * Master eBPF, XDP & Network Observability Orchestration Facade.
 */
class EbpfManager
{
    private EbpfVm $vm;
    private EbpfVerifier $verifier;
    private XdpPipeline $xdpPipeline;
    private FlowMonitor $flowMonitor;
    private HttpInspector $httpInspector;
    /** @var array<string, MapInterface> */
    private array $maps = [];
    /** @var array<string, EbpfProgram> */
    private array $programs = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config        = $config;
        $this->vm            = new EbpfVm();
        $this->verifier      = new EbpfVerifier();
        $this->xdpPipeline   = new XdpPipeline($this->vm);
        $this->flowMonitor   = new FlowMonitor();
        $this->httpInspector = new HttpInspector();
    }

    public function createProgram(string $name): EbpfProgram
    {
        $prog = new EbpfProgram($name);
        $this->programs[$name] = $prog;
        return $prog;
    }

    public function loadProgram(EbpfProgram $program): bool
    {
        $this->verifier->verify($program);
        $this->programs[$program->getName()] = $program;
        return true;
    }

    public function attachXdp(EbpfProgram $program): void
    {
        $this->loadProgram($program);
        $this->xdpPipeline->attachProgram($program);
    }

    public function createArrayMap(string $name, int $entries = 1024): ArrayMap
    {
        $map = new ArrayMap($name, $entries);
        $this->maps[$name] = $map;
        return $map;
    }

    public function createHashMap(string $name, int $entries = 10000): HashMap
    {
        $map = new HashMap($name, $entries);
        $this->maps[$name] = $map;
        return $map;
    }

    public function createLruMap(string $name, int $entries = 1000): LruHashMap
    {
        $map = new LruHashMap($name, $entries);
        $this->maps[$name] = $map;
        return $map;
    }

    public function createRingBuffer(string $name, int $entries = 4096): RingBufferMap
    {
        $map = new RingBufferMap($name, $entries);
        $this->maps[$name] = $map;
        return $map;
    }

    public function getMap(string $name): ?MapInterface
    {
        return $this->maps[$name] ?? null;
    }

    public function getVm(): EbpfVm
    {
        return $this->vm;
    }

    public function getVerifier(): EbpfVerifier
    {
        return $this->verifier;
    }

    public function getXdpPipeline(): XdpPipeline
    {
        return $this->xdpPipeline;
    }

    public function getFlowMonitor(): FlowMonitor
    {
        return $this->flowMonitor;
    }

    public function getHttpInspector(): HttpInspector
    {
        return $this->httpInspector;
    }

    public function filterPacket(Packet $packet): XdpAction
    {
        return $this->xdpPipeline->processPacket($packet);
    }
}
