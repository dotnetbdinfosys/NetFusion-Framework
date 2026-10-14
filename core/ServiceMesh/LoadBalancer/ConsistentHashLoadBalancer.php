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

class ConsistentHashLoadBalancer implements LoadBalancerInterface
{
    protected string $hashHeader;

    public function __construct(string $hashHeader = 'X-User-Id')
    {
        $this->hashHeader = $hashHeader;
    }

    public function choose(array $instances, ?MeshRequestContext $context = null): ?ServiceInstance
    {
        $available = array_values(array_filter($instances, fn(ServiceInstance $i) => $i->isAvailable()));
        if (empty($available)) {
            return null;
        }

        $hashKey = ($context !== null ? $context->getHeader($this->hashHeader) : null)
            ?? ($context !== null ? $context->getPath() : 'default');

        $hash = crc32($hashKey);
        $index = abs($hash) % count($available);

        return $available[$index];
    }

    public function getName(): string
    {
        return 'consistent_hash';
    }
}
