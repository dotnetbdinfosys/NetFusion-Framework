<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Registry/Drivers/FileRegistryDriver.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Registry\Drivers
| Purpose   : File-backed service registry driver sharing state across local processes.
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

namespace NetFusion\Core\RPC\Registry\Drivers;

use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\Registry\ServiceInstance;

class FileRegistryDriver implements ServiceRegistryInterface
{
    public function __construct(
        protected string $filePath
    ) {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([]));
        }
    }

    /**
     * @return array<string, array<string, array>>
     */
    protected function loadData(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $raw = file_get_contents($this->filePath);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }

    protected function saveData(array $data): void
    {
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    public function register(ServiceInstance $instance): void
    {
        $data = $this->loadData();
        $data[$instance->serviceName][$instance->id] = $instance->toArray();
        $this->saveData($data);
    }

    public function deregister(string $instanceId): void
    {
        $data = $this->loadData();
        foreach ($data as $serviceName => $instances) {
            if (isset($instances[$instanceId])) {
                unset($data[$serviceName][$instanceId]);
                if (empty($data[$serviceName])) {
                    unset($data[$serviceName]);
                }
                $this->saveData($data);
                return;
            }
        }
    }

    public function heartbeat(string $instanceId): bool
    {
        $data = $this->loadData();
        foreach ($data as $serviceName => $instances) {
            if (isset($instances[$instanceId])) {
                $data[$serviceName][$instanceId]['last_heartbeat'] = microtime(true);
                $this->saveData($data);
                return true;
            }
        }
        return false;
    }

    public function discover(string $serviceName): array
    {
        $data = $this->loadData();
        if (!isset($data[$serviceName])) {
            return [];
        }

        $healthy = [];
        foreach ($data[$serviceName] as $item) {
            $instance = ServiceInstance::fromArray($item);
            if ($instance->isHealthy()) {
                $healthy[] = $instance;
            }
        }
        return $healthy;
    }

    public function all(): array
    {
        $data = $this->loadData();
        $result = [];
        foreach ($data as $serviceName => $instances) {
            foreach ($instances as $item) {
                $result[$serviceName][] = ServiceInstance::fromArray($item);
            }
        }
        return $result;
    }

    public function pruneExpired(int $ttlSeconds = 30): int
    {
        $data = $this->loadData();
        $purged = 0;
        foreach ($data as $serviceName => $instances) {
            foreach ($instances as $id => $item) {
                $instance = ServiceInstance::fromArray($item);
                if (!$instance->isHealthy($ttlSeconds)) {
                    unset($data[$serviceName][$id]);
                    $purged++;
                }
            }
            if (empty($data[$serviceName])) {
                unset($data[$serviceName]);
            }
        }
        $this->saveData($data);
        return $purged;
    }
}
