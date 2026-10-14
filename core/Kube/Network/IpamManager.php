<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Network;

use NetFusion\Core\Kube\Exceptions\SchedulerException;
use NetFusion\Core\Kube\Models\Pod;

/**
 * IP Address Management (IPAM) for Kubernetes Pod CNI fabric.
 * Manages allocation and deallocation of virtual IPs across cluster subnets.
 */
class IpamManager
{
    /**
     * @var array<string, string> Map of Pod UID => allocated IP address
     */
    protected array $allocations = [];

    /**
     * @var array<string, string> Map of IP address => Pod UID
     */
    protected array $reverseAllocations = [];

    protected int $nextHostId = 10;

    /**
     * @param string $subnetPrefix First two octets (default: "10.244")
     * @param int $subnetThirdOctet Third octet base (default: 0)
     */
    public function __construct(
        protected string $subnetPrefix = '10.244',
        protected int $subnetThirdOctet = 0
    ) {
    }

    /**
     * Allocate a unique virtual IP for the given Pod.
     */
    public function allocateIp(Pod $pod): string
    {
        $uid = $pod->getUid();
        if (isset($this->allocations[$uid])) {
            $existing = $this->allocations[$uid];
            $pod->setIp($existing);
            return $existing;
        }

        // Generate next available IP
        $oct3 = $this->subnetThirdOctet;
        $oct4 = $this->nextHostId;

        // Rollover if host ID exceeds 254
        if ($oct4 > 254) {
            $this->subnetThirdOctet++;
            $this->nextHostId = 10;
            $oct3 = $this->subnetThirdOctet;
            $oct4 = $this->nextHostId;
        }

        $ip = "{$this->subnetPrefix}.{$oct3}.{$oct4}";
        $this->nextHostId++;

        $this->allocations[$uid] = $ip;
        $this->reverseAllocations[$ip] = $uid;

        $pod->setIp($ip);

        return $ip;
    }

    /**
     * Release virtual IP previously allocated to a Pod.
     */
    public function releaseIp(Pod $pod): void
    {
        $uid = $pod->getUid();
        if (isset($this->allocations[$uid])) {
            $ip = $this->allocations[$uid];
            unset($this->allocations[$uid]);
            unset($this->reverseAllocations[$ip]);
            $pod->setIp(null);
        }
    }

    /**
     * Check if an IP address is currently allocated.
     */
    public function isAllocated(string $ip): bool
    {
        return isset($this->reverseAllocations[$ip]);
    }

    /**
     * Get total active IP allocations count.
     */
    public function getAllocatedCount(): int
    {
        return count($this->allocations);
    }
}
