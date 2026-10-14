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

namespace NetFusion\Core\Network\Ebpf\Observability;

use NetFusion\Core\Network\Ebpf\Maps\RingBufferMap;
use NetFusion\Core\Network\Ebpf\Xdp\Packet;

/**
 * L4 Network Flow Monitor & Socket Interception Simulator.
 * Tracks TCP connection state, RTT latency, packet loss, and dispatches events via eBPF RingBuffer.
 */
class FlowMonitor
{
    /** @var array<string, FlowRecord> flowId => Record */
    private array $flows = [];
    private RingBufferMap $ringBuffer;

    public function __construct(?RingBufferMap $ringBuffer = null)
    {
        $this->ringBuffer = $ringBuffer ?? new RingBufferMap('flow_events', 10000);
    }

    public function trackPacket(Packet $packet, float $rttMs = 2.0, bool $isRetransmit = false): FlowRecord
    {
        $flowId = "{$packet->getSrcIp()}:{$packet->getSrcPort()}->{$packet->getDstIp()}:{$packet->getDstPort()}";

        if (!isset($this->flows[$flowId])) {
            $this->flows[$flowId] = new FlowRecord(
                $packet->getSrcIp(),
                $packet->getSrcPort(),
                $packet->getDstIp(),
                $packet->getDstPort(),
                $packet->getProtocol()
            );
        }

        $record = $this->flows[$flowId];
        $record->recordPacket($packet->getSize(), $rttMs, $isRetransmit);

        // Push event into ring buffer
        $this->ringBuffer->push([
            'event'     => 'PACKET_SEEN',
            'flow_id'   => $flowId,
            'bytes'     => $packet->getSize(),
            'timestamp' => microtime(true),
        ]);

        return $record;
    }

    public function getFlow(string $flowId): ?FlowRecord
    {
        return $this->flows[$flowId] ?? null;
    }

    public function getFlows(): array
    {
        return $this->flows;
    }

    public function getRingBuffer(): RingBufferMap
    {
        return $this->ringBuffer;
    }

    public function clear(): void
    {
        $this->flows = [];
        $this->ringBuffer->clear();
    }
}
