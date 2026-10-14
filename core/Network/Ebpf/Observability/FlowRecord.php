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

/**
 * L4/L7 Network Flow Record.
 */
class FlowRecord
{
    private string $flowId;
    private string $srcIp;
    private int $srcPort;
    private string $dstIp;
    private int $dstPort;
    private string $protocol;
    private int $packets = 0;
    private int $bytes = 0;
    private float $rttMs = 0.0;
    private int $retransmits = 0;
    private float $startTime;
    private float $lastSeen;

    public function __construct(
        string $srcIp,
        int $srcPort,
        string $dstIp,
        int $dstPort,
        string $protocol = 'TCP'
    ) {
        $this->flowId    = "{$srcIp}:{$srcPort}->{$dstIp}:{$dstPort}";
        $this->srcIp     = $srcIp;
        $this->srcPort   = $srcPort;
        $this->dstIp     = $dstIp;
        $this->dstPort   = $dstPort;
        $this->protocol  = $protocol;
        $this->startTime = microtime(true);
        $this->lastSeen  = $this->startTime;
    }

    public function recordPacket(int $bytes, float $rttMs = 1.5, bool $isRetransmit = false): void
    {
        $this->packets++;
        $this->bytes += $bytes;
        $this->rttMs = $rttMs;
        if ($isRetransmit) {
            $this->retransmits++;
        }
        $this->lastSeen = microtime(true);
    }

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function getPackets(): int
    {
        return $this->packets;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function getRttMs(): float
    {
        return $this->rttMs;
    }

    public function getRetransmits(): int
    {
        return $this->retransmits;
    }

    public function toArray(): array
    {
        return [
            'flow_id'     => $this->flowId,
            'src'         => "{$this->srcIp}:{$this->srcPort}",
            'dst'         => "{$this->dstIp}:{$this->dstPort}",
            'protocol'    => $this->protocol,
            'packets'     => $this->packets,
            'bytes'       => $this->bytes,
            'rtt_ms'      => round($this->rttMs, 2),
            'retransmits' => $this->retransmits,
            'duration_ms' => round(($this->lastSeen - $this->startTime) * 1000, 2),
        ];
    }
}
