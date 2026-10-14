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

namespace NetFusion\Core\IMDG;

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\IMDG\Compute\EntryProcessor;
use NetFusion\Core\IMDG\Contracts\DistributedMapInterface;
use NetFusion\Core\IMDG\Contracts\EntryProcessorInterface;
use NetFusion\Core\IMDG\Contracts\Store\GridPersistenceStoreInterface;
use NetFusion\Core\IMDG\NearCache\NearCacheConfig;
use NetFusion\Core\IMDG\Partitioning\MemberNode;
use NetFusion\Core\IMDG\Store\ArrayGridStore;
use NetFusion\Core\IMDG\Store\DatabaseGridStore;

class IMDGManager
{
    /** @var array<string, GridCluster> */
    protected array $clusters = [];
    protected string $defaultCluster = 'default';
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function cluster(?string $name = null): GridCluster
    {
        $name = $name ?? $this->defaultCluster;

        if (!isset($this->clusters[$name])) {
            $this->clusters[$name] = $this->createCluster($name);
        }

        return $this->clusters[$name];
    }

    public function map(string $name, ?string $clusterName = null): DistributedMapInterface
    {
        return $this->cluster($clusterName)->getMap($name);
    }

    public function get(string $map, string $key, mixed $default = null, ?string $clusterName = null): mixed
    {
        return $this->map($map, $clusterName)->get($key, $default);
    }

    public function put(string $map, string $key, mixed $value, int $ttl = 0, ?string $clusterName = null): mixed
    {
        return $this->map($map, $clusterName)->put($key, $value, $ttl);
    }

    public function remove(string $map, string $key, ?string $clusterName = null): mixed
    {
        return $this->map($map, $clusterName)->remove($key);
    }

    public function execute(string $map, string $key, callable|EntryProcessorInterface $processor, ?string $clusterName = null): mixed
    {
        $ep = is_callable($processor) ? new EntryProcessor($processor) : $processor;
        return $this->map($map, $clusterName)->executeOnKey($key, $ep);
    }

    public function vectorSearch(string $map, array $vector, int $topK = 5, float $minScore = 0.0, ?string $clusterName = null): array
    {
        $dMap = $this->map($map, $clusterName);
        if ($dMap instanceof DistributedMap) {
            return $dMap->searchVector($vector, $topK, $minScore);
        }
        return [];
    }

    public function stats(?string $clusterName = null): array
    {
        return $this->cluster($clusterName)->getStats();
    }

    protected function createCluster(string $name): GridCluster
    {
        $clusterConf = $this->config['clusters'][$name] ?? $this->config;

        $clusterName = $clusterConf['name'] ?? "netfusion-grid-{$name}";
        $nodeId = $clusterConf['node_id'] ?? 'node-' . gethostname() . '-' . substr(md5($name), 0, 4);
        $host = $clusterConf['host'] ?? '127.0.0.1';
        $port = (int) ($clusterConf['port'] ?? 5701);
        $partitions = (int) ($clusterConf['partitions'] ?? 271);
        $backups = (int) ($clusterConf['backups'] ?? 1);

        $localNode = new MemberNode($nodeId, $host, $port, 1, true);

        // Store configuration
        $storeDriver = $clusterConf['store'] ?? 'array';
        $store = $this->resolveStore($storeDriver);

        $cluster = new GridCluster($clusterName, $localNode, $partitions, $backups, $store);

        // Near-Cache default
        if (!empty($clusterConf['near_cache']['enabled'])) {
            $ncConf = new NearCacheConfig(
                (int) ($clusterConf['near_cache']['max_size'] ?? 5000),
                (int) ($clusterConf['near_cache']['ttl'] ?? 300),
                (int) ($clusterConf['near_cache']['tti'] ?? 60),
                (string) ($clusterConf['near_cache']['eviction'] ?? 'lru'),
                (bool) ($clusterConf['near_cache']['invalidate_on_change'] ?? true)
            );
            $cluster->setDefaultNearCacheConfig($ncConf);
        }

        if (isset($clusterConf['write_mode'])) {
            $cluster->setDefaultWriteMode($clusterConf['write_mode']);
        }

        // Additional member nodes
        if (!empty($clusterConf['members']) && is_array($clusterConf['members'])) {
            foreach ($clusterConf['members'] as $mConf) {
                if (($mConf['id'] ?? '') !== $nodeId) {
                    $node = new MemberNode(
                        $mConf['id'],
                        $mConf['host'] ?? '127.0.0.1',
                        (int) ($mConf['port'] ?? 5701),
                        (int) ($mConf['weight'] ?? 1),
                        false
                    );
                    $cluster->addMember($node);
                }
            }
        }

        return $cluster;
    }

    protected function resolveStore(string $driver): GridPersistenceStoreInterface
    {
        if ($driver === 'database') {
            if (class_exists(Application::class) && Application::getInstance() && Application::getInstance()->has('db')) {
                $db = Application::getInstance()->make('db');
                return new DatabaseGridStore($db->connection());
            }
        }
        return new ArrayGridStore();
    }
}
