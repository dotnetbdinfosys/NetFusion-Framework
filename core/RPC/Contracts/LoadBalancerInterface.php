<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Contracts/LoadBalancerInterface.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Contracts
| Purpose   : Interface for routing RPC requests among multiple service instances.
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

namespace NetFusion\Core\RPC\Contracts;

use NetFusion\Core\RPC\Registry\ServiceInstance;

interface LoadBalancerInterface
{
    /**
     * Select a single service instance from a pool of healthy instances.
     *
     * @param array<ServiceInstance> $instances
     * @param string                 $serviceName
     * @return ServiceInstance|null
     */
    public function select(array $instances, string $serviceName): ?ServiceInstance;
}
