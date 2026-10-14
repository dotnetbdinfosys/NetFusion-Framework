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
 * Latency-Aware load balancer.
 * Routes to the healthy upstream with the lowest exponential moving average
 * response time. Falls back to Round-Robin when all upstreams have zero latency.
 */
class LatencyAwareBalancer implements LoadBalancerInterface
{
    private RoundRobinBalancer $fallback;

    public function __construct()
    {
        $this->fallback = new RoundRobinBalancer();
    }

    public function select(UpstreamPool $pool, GatewayRequest $request): ?UpstreamInterface
    {
        $healthy = $pool->healthy();
        if (empty($healthy)) {
            return null;
        }

        // Check if any upstream has recorded latency
        $hasLatency = false;
        foreach ($healthy as $upstream) {
            /** @var Upstream $upstream */
            if ($upstream->getAverageLatencyMs() > 0.0) {
                $hasLatency = true;
                break;
            }
        }

        // Fall back to round-robin when no latency data available yet
        if (!$hasLatency) {
            return $this->fallback->select($pool, $request);
        }

        $best      = null;
        $minLatency = PHP_FLOAT_MAX;

        foreach ($healthy as $upstream) {
            /** @var Upstream $upstream */
            $latency = $upstream->getAverageLatencyMs();
            if ($latency < $minLatency) {
                $minLatency = $latency;
                $best       = $upstream;
            }
        }

        return $best;
    }

    public function getName(): string
    {
        return 'latency_aware';
    }
}
