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

/**
 * Raw L3/L4 Network Packet representation.
 */
class Packet
{
    private string $srcIp;
    private string $dstIp;
    private int $srcPort;
    private int $dstPort;
    private string $protocol; // 'TCP', 'UDP', 'ICMP'
    private string $payload;
    private int $size;
    private bool $synFlag;

    public function __construct(
        string $srcIp,
        string $dstIp,
        int $srcPort = 0,
        int $dstPort = 80,
        string $protocol = 'TCP',
        string $payload = '',
        bool $synFlag = false
    ) {
        $this->srcIp    = $srcIp;
        $this->dstIp    = $dstIp;
        $this->srcPort  = $srcPort;
        $this->dstPort  = $dstPort;
        $this->protocol = strtoupper($protocol);
        $this->payload  = $payload;
        $this->size     = strlen($payload) + 40; // 20B IP + 20B TCP header
        $this->synFlag  = $synFlag;
    }

    public static function tcpSyn(string $srcIp, string $dstIp, int $srcPort, int $dstPort): self
    {
        return new self($srcIp, $dstIp, $srcPort, $dstPort, 'TCP', '', true);
    }

    public function getSrcIp(): string
    {
        return $this->srcIp;
    }

    public function getDstIp(): string
    {
        return $this->dstIp;
    }

    public function getSrcPort(): int
    {
        return $this->srcPort;
    }

    public function getDstPort(): int
    {
        return $this->dstPort;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isSyn(): bool
    {
        return $this->synFlag;
    }
}
