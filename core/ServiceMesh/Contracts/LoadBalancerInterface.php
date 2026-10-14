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

namespace NetFusion\Core\ServiceMesh\Contracts;

use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;

interface LoadBalancerInterface
{
    /**
     * Choose a healthy service instance from available candidates.
     *
     * @param array<ServiceInstance> $instances
     * @param MeshRequestContext|null $context
     */
    public function choose(array $instances, ?MeshRequestContext $context = null): ?ServiceInstance;

    /**
     * Get load balancer strategy name.
     */
    public function getName(): string;
}
