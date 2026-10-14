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

namespace NetFusion\Core\ServiceMesh\LoadBalancer;

use NetFusion\Core\ServiceMesh\Contracts\LoadBalancerInterface;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;

class LeastConnectionLoadBalancer implements LoadBalancerInterface
{
    public function choose(array $instances, ?MeshRequestContext $context = null): ?ServiceInstance
    {
        $available = array_values(array_filter($instances, fn(ServiceInstance $i) => $i->isAvailable()));
        if (empty($available)) {
            return null;
        }

        $minInstance = $available[0];
        $minConns = $minInstance->getActiveConnections();

        foreach ($available as $inst) {
            if ($inst->getActiveConnections() < $minConns) {
                $minInstance = $inst;
                $minConns = $inst->getActiveConnections();
            }
        }

        return $minInstance;
    }

    public function getName(): string
    {
        return 'least_conn';
    }
}
