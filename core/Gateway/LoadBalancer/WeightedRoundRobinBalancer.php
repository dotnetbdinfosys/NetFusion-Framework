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
 * Weighted Round-Robin load balancer.
 * Higher weight upstreams receive proportionally more requests.
 * Uses the Smooth Weighted Round-Robin (SWRR) algorithm for even distribution.
 */
class WeightedRoundRobinBalancer implements LoadBalancerInterface
{
    /** @var array<string, array<string, int>> Per-pool current weights */
    private array $currentWeights = [];

    public function select(UpstreamPool $pool, GatewayRequest $request): ?UpstreamInterface
    {
        $healthy = $pool->healthy();
        if (empty($healthy)) {
            return null;
        }

        $key = $pool->getName();

        // Initialize current weights for new pool
        if (!isset($this->currentWeights[$key])) {
            $this->currentWeights[$key] = [];
            foreach ($healthy as $upstream) {
                $this->currentWeights[$key][$upstream->getId()] = 0;
            }
        }

        // Ensure all upstreams have entries
        foreach ($healthy as $upstream) {
            if (!isset($this->currentWeights[$key][$upstream->getId()])) {
                $this->currentWeights[$key][$upstream->getId()] = 0;
            }
        }

        $totalWeight = 0;
        foreach ($healthy as $upstream) {
            $totalWeight += $upstream->getWeight();
        }

        // SWRR: add each upstream's weight to its current weight, pick highest
        $bestUpstream   = null;
        $bestWeight     = PHP_INT_MIN;

        foreach ($healthy as $upstream) {
            $id = $upstream->getId();
            $this->currentWeights[$key][$id] += $upstream->getWeight();
            if ($this->currentWeights[$key][$id] > $bestWeight) {
                $bestWeight   = $this->currentWeights[$key][$id];
                $bestUpstream = $upstream;
            }
        }

        // Reduce chosen upstream's current weight by total
        if ($bestUpstream !== null) {
            $this->currentWeights[$key][$bestUpstream->getId()] -= $totalWeight;
        }

        return $bestUpstream;
    }

    public function getName(): string
    {
        return 'weighted_round_robin';
    }
}
