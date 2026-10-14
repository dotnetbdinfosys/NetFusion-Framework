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

namespace NetFusion\Core\Gateway\LoadBalancer;

use NetFusion\Core\Gateway\Contracts\LoadBalancerInterface;
use NetFusion\Core\Gateway\Contracts\UpstreamInterface;
use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\UpstreamPool;

/**
 * IP-Hash load balancer.
 * Uses a deterministic hash of the client IP to always route the same
 * client to the same upstream (sticky sessions without cookies).
 */
class IpHashBalancer implements LoadBalancerInterface
{
    public function select(UpstreamPool $pool, GatewayRequest $request): ?UpstreamInterface
    {
        $healthy = $pool->healthy();
        if (empty($healthy)) {
            return null;
        }

        // crc32 gives a stable integer hash of the client IP
        $hash  = abs(crc32($request->getClientIp()));
        $index = $hash % count($healthy);

        return $healthy[$index];
    }

    public function getName(): string
    {
        return 'ip_hash';
    }
}
