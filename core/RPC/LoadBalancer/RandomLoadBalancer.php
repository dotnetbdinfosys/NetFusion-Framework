<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/LoadBalancer/RandomLoadBalancer.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\LoadBalancer
| Purpose   : Randomized service instance selector.
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

class RandomLoadBalancer implements LoadBalancerInterface
{
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
        $index = random_int(0, $count - 1);

        return $instances[$index] ?? $instances[0];
    }
}
