<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Consul\Catalog;

use NetFusion\Core\Consul\Enums\CheckStatus;
use NetFusion\Core\Consul\Exceptions\ServiceNotFoundException;
use NetFusion\Core\Consul\Models\ServiceInstance;

/**
 * Central Service Discovery Catalog.
 * Stores, indexes, filters and load-balances registered service instances across the infrastructure.
 */
class ServiceCatalog
{
    /** @var array<string, array<string, ServiceInstance>> [serviceName => [instanceId => ServiceInstance]] */
    protected array $services = [];

    /** @var array<string, int> Round-robin counter per service name */
    protected array $rrCounters = [];

    /**
     * Register a service instance in the catalog.
     */
    public function registerService(ServiceInstance $instance): self
    {
        $name = strtolower($instance->getName());
        $id = $instance->getId();

        if (!isset($this->services[$name])) {
            $this->services[$name] = [];
            $this->rrCounters[$name] = 0;
        }

        $this->services[$name][$id] = $instance;
        return $this;
    }

    /**
     * Deregister a service instance by ID.
     */
    public function deregisterService(string $instanceId): bool
    {
        foreach ($this->services as $name => &$instances) {
            if (isset($instances[$instanceId])) {
                unset($instances[$instanceId]);
                if (empty($instances)) {
                    unset($this->services[$name]);
                    unset($this->rrCounters[$name]);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a service name exists in the catalog.
     */
    public function hasService(string $serviceName): bool
    {
        $name = strtolower($serviceName);
        return !empty($this->services[$name]);
    }

    /**
     * Find a specific instance by its ID.
     */
    public function getInstance(string $instanceId): ?ServiceInstance
    {
        foreach ($this->services as $instances) {
            if (isset($instances[$instanceId])) {
                return $instances[$instanceId];
            }
        }
        return null;
    }

    /**
     * Get all instances registered for a service name.
     *
     * @return array<string, ServiceInstance>
     * @throws ServiceNotFoundException
     */
    public function getServiceInstances(string $serviceName): array
    {
        $name = strtolower($serviceName);
        if (!isset($this->services[$name]) || empty($this->services[$name])) {
            throw new ServiceNotFoundException($serviceName);
        }

        return $this->services[$name];
    }

    /**
     * Return all service names and their registered count.
     *
     * @return array<string, array<string>> [serviceName => [tags]]
     */
    public function listServices(): array
    {
        $result = [];
        foreach ($this->services as $name => $instances) {
            $allTags = [];
            foreach ($instances as $inst) {
                foreach ($inst->getTags() as $tag) {
                    $allTags[$tag] = true;
                }
            }
            $result[$name] = array_keys($allTags);
        }
        return $result;
    }

    /**
     * Filter instances of a service by required tags.
     *
     * @param array<string> $tags
     * @return array<ServiceInstance>
     */
    public function filterByTags(string $serviceName, array $tags): array
    {
        $instances = $this->getServiceInstances($serviceName);
        if (empty($tags)) {
            return array_values($instances);
        }

        return array_values(array_filter($instances, function (ServiceInstance $inst) use ($tags) {
            foreach ($tags as $tag) {
                if (!$inst->hasTag($tag)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Return only healthy (PASSING, or optionally WARNING) instances of a service.
     *
     * @return array<ServiceInstance>
     */
    public function selectHealthy(string $serviceName, bool $allowWarning = true): array
    {
        $instances = $this->getServiceInstances($serviceName);

        return array_values(array_filter($instances, function (ServiceInstance $inst) use ($allowWarning) {
            if ($inst->getStatus() === CheckStatus::PASSING) {
                return true;
            }
            if ($allowWarning && $inst->getStatus() === CheckStatus::WARNING) {
                return true;
            }
            return false;
        }));
    }

    /**
     * Load balance across healthy instances of a service using round-robin or weighted selection.
     *
     * @param string $strategy 'round_robin', 'weighted', or 'random'
     * @throws ServiceNotFoundException
     */
    public function loadBalance(string $serviceName, string $strategy = 'round_robin', bool $allowWarning = true): ?ServiceInstance
    {
        $healthy = $this->selectHealthy($serviceName, $allowWarning);
        if (empty($healthy)) {
            return null;
        }

        if (count($healthy) === 1) {
            return $healthy[0];
        }

        $name = strtolower($serviceName);

        switch (strtolower($strategy)) {
            case 'random':
                return $healthy[array_rand($healthy)];

            case 'weighted':
                $pool = [];
                foreach ($healthy as $inst) {
                    $weight = max(1, $inst->getEffectiveWeight());
                    for ($i = 0; $i < $weight; $i++) {
                        $pool[] = $inst;
                    }
                }
                return empty($pool) ? $healthy[0] : $pool[array_rand($pool)];

            case 'round_robin':
            default:
                if (!isset($this->rrCounters[$name])) {
                    $this->rrCounters[$name] = 0;
                }
                $idx = $this->rrCounters[$name] % count($healthy);
                $this->rrCounters[$name]++;
                return $healthy[$idx];
        }
    }

    /**
     * Clear all services from catalog.
     */
    public function clear(): self
    {
        $this->services = [];
        $this->rrCounters = [];
        return $this;
    }

    /**
     * Total number of registered service instances.
     */
    public function countInstances(): int
    {
        $count = 0;
        foreach ($this->services as $instances) {
            $count += count($instances);
        }
        return $count;
    }
}
