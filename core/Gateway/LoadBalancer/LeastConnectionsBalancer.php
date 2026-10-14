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
use NetFusion\Core\Gateway\Models\Upstream;
use NetFusion\Core\Gateway\Models\UpstreamPool;

/**
 * Least-Connections load balancer.
 * Routes each request to the healthy upstream with the fewest active connections.
 */
class LeastConnectionsBalancer implements LoadBalancerInterface
{
    public function select(UpstreamPool $pool, GatewayRequest $request): ?UpstreamInterface
    {
        $healthy = $pool->healthy();
        if (empty($healthy)) {
            return null;
        }

        $best        = null;
        $minConns    = PHP_INT_MAX;

        foreach ($healthy as $upstream) {
            /** @var Upstream $upstream */
            $conns = $upstream->getActiveConnections();
            if ($conns < $minConns) {
                $minConns = $conns;
                $best     = $upstream;
            }
        }

        return $best;
    }

    public function getName(): string
    {
        return 'least_connections';
    }
}
