<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/LoadBalancer/RoundRobinLoadBalancer.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\LoadBalancer
| Purpose   : Sequential atomic round-robin load balancer for microservice clusters.
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

class RoundRobinLoadBalancer implements LoadBalancerInterface
{
    /**
     * @var array<string, int>
     */
    protected array $counters = [];

    public function select(array $instances, string $serviceName): ?ServiceInstance
    {
        $count = count($instances);
        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return reset($instances);
        }

        $instances = array_values($instances);

        if (!isset($this->counters[$serviceName])) {
            $this->counters[$serviceName] = 0;
        }

        $index = $this->counters[$serviceName] % $count;
        $this->counters[$serviceName]++;

        return $instances[$index] ?? $instances[0];
    }
}
