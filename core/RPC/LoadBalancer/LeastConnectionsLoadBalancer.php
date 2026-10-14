<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/LoadBalancer/LeastConnectionsLoadBalancer.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\LoadBalancer
| Purpose   : Selects service instance with least active concurrent connections.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\RPC\LoadBalancer;

use NetFusion\Core\RPC\Contracts\LoadBalancerInterface;
use NetFusion\Core\RPC\Registry\ServiceInstance;

class LeastConnectionsLoadBalancer implements LoadBalancerInterface
{
    public function select(array $instances, string $serviceName): ?ServiceInstance
    {
        if (empty($instances)) {
            return null;
        }

        $minConn = null;
        $best = null;

        foreach ($instances as $instance) {
            $conn = $instance->activeConnections;
            if ($minConn === null || $conn < $minConn) {
                $minConn = $conn;
                $best = $instance;
            }
        }

        return $best ?? reset($instances);
    }
}
