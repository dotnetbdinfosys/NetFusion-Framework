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
 * Classic Round-Robin load balancer.
 * Distributes requests evenly across healthy upstreams in a circular fashion.
 */
class RoundRobinBalancer implements LoadBalancerInterface
{
    /** @var array<string, int> Per-pool current index counter */
    private array $counters = [];

    public function select(UpstreamPool $pool, GatewayRequest $request): ?UpstreamInterface
    {
        $healthy = $pool->healthy();
        if (empty($healthy)) {
            return null;
        }

        $key   = $pool->getName();
        $count = count($healthy);

        if (!isset($this->counters[$key])) {
            $this->counters[$key] = 0;
        }

        $upstream = $healthy[$this->counters[$key] % $count];
        $this->counters[$key] = ($this->counters[$key] + 1) % PHP_INT_MAX;

        return $upstream;
    }

    public function getName(): string
    {
        return 'round_robin';
    }
}
