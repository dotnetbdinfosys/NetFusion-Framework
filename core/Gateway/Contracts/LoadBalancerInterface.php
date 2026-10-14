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

namespace NetFusion\Core\Gateway\Contracts;

use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\UpstreamPool;

interface LoadBalancerInterface
{
    /**
     * Selects an upstream from the pool for the given request.
     * Returns null when no healthy upstream is available.
     */
    public function select(UpstreamPool $pool, GatewayRequest $request): ?UpstreamInterface;

    /**
     * Returns the name/strategy identifier of this load balancer.
     */
    public function getName(): string;
}
