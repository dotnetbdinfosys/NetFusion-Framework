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
 * XDP Packet Context (struct xdp_md).
 * In kernel, holds pointers: data, data_end, data_meta, ingress_ifindex.
 */
class XdpContext
{
    private Packet $packet;
    private int $data;
    private int $dataEnd;
    private int $ingressIfindex;

    public function __construct(Packet $packet, int $ingressIfindex = 1)
    {
        $this->packet         = $packet;
        $this->data           = 0;
        $this->dataEnd        = $packet->getSize();
        $this->ingressIfindex = $ingressIfindex;
    }

    public function getPacket(): Packet
    {
        return $this->packet;
    }

    public function getData(): int
    {
        return $this->data;
    }

    public function getDataEnd(): int
    {
        return $this->dataEnd;
    }

    public function getLength(): int
    {
        return $this->dataEnd - $this->data;
    }

    public function getIngressIfindex(): int
    {
        return $this->ingressIfindex;
    }

    public function toArray(): array
    {
        return [
            0 => $this->data,
            1 => $this->dataEnd,
            2 => $this->ingressIfindex,
            3 => $this->packet->getSrcPort(),
            4 => $this->packet->getDstPort(),
            5 => $this->packet->isSyn() ? 1 : 0,
            6 => ip2long($this->packet->getSrcIp()) ?: 0,
            7 => ip2long($this->packet->getDstIp()) ?: 0,
        ];
    }
}
