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

use NetFusion\Core\IMDG\Contracts\DistributedMapInterface;
use NetFusion\Core\IMDG\Contracts\GridInterface;
use NetFusion\Core\IMDG\Contracts\Store\GridPersistenceStoreInterface;
use NetFusion\Core\IMDG\NearCache\NearCache;
use NetFusion\Core\IMDG\NearCache\NearCacheConfig;
use NetFusion\Core\IMDG\Partitioning\MemberNode;
use NetFusion\Core\IMDG\Partitioning\PartitionMap;
use NetFusion\Core\IMDG\Store\ArrayGridStore;

class GridCluster implements GridInterface
{
    protected string $clusterName;
    protected MemberNode $localNode;
    /** @var array<string, MemberNode> */
    protected array $members = [];
    protected PartitionMap $partitionMap;
    /** @var array<string, DistributedMap> */
    protected array $maps = [];
    protected GridPersistenceStoreInterface $persistenceStore;
    protected ?NearCacheConfig $defaultNearCacheConfig = null;
    protected string $defaultWriteMode = 'none';

    public function __construct(
        string $clusterName = 'netfusion-grid',
        ?MemberNode $localNode = null,
        int $partitionCount = 271,
        int $backupCount = 1,
        ?GridPersistenceStoreInterface $persistenceStore = null
    ) {
        $this->clusterName = $clusterName;
        $this->localNode = $localNode ?? new MemberNode('node-local', '127.0.0.1', 5701, 1, true);
        $this->members[$this->localNode->getId()] = $this->localNode;
        $this->partitionMap = new PartitionMap($partitionCount, $backupCount);
        $this->persistenceStore = $persistenceStore ?? new ArrayGridStore();
        $this->partitionMap->rebalance($this->members);
    }

    public function getClusterName(): string
    {
        return $this->clusterName;
    }

    public function getLocalNode(): MemberNode
    {
        return $this->localNode;
    }

    public function getPartitionMap(): PartitionMap
    {
        return $this->partitionMap;
    }

    public function getPersistenceStore(): GridPersistenceStoreInterface
    {
        return $this->persistenceStore;
    }

    public function setPersistenceStore(GridPersistenceStoreInterface $store): self
    {
        $this->persistenceStore = $store;
        return $this;
    }

    public function setDefaultNearCacheConfig(?NearCacheConfig $config): self
    {
        $this->defaultNearCacheConfig = $config;
        return $this;
    }

    public function setDefaultWriteMode(string $mode): self
    {
        $this->defaultWriteMode = strtolower($mode);
        return $this;
    }

    public function getMap(string $name, ?NearCacheConfig $nearConfig = null): DistributedMapInterface
    {
        if (isset($this->maps[$name])) {
            return $this->maps[$name];
        }

        $config = $nearConfig ?? $this->defaultNearCacheConfig;
        $nearCache = ($config !== null) ? new NearCache($name, $config) : null;

        $map = new DistributedMap(
            $name,
            $this->partitionMap,
            $nearCache,
            $this->persistenceStore,
            $this->defaultWriteMode
        );

        $this->maps[$name] = $map;
        return $map;
    }

    public function addMember(MemberNode $node): self
    {
        $this->members[$node->getId()] = $node;
        $this->rebalance();
        return $this;
    }

    public function removeMember(string $nodeId): self
    {
        unset($this->members[$nodeId]);
        $this->rebalance();
        return $this;
    }

    public function getMembers(): array
    {
        return $this->members;
    }

    public function getMember(string $nodeId): ?MemberNode
    {
        return $this->members[$nodeId] ?? null;
    }

    public function heartbeat(string $nodeId): void
    {
        if (isset($this->members[$nodeId])) {
            $this->members[$nodeId]->beat();
        }
    }

    public function rebalance(): void
    {
        $this->partitionMap->rebalance($this->members);
    }

    public function clearAll(): void
    {
        foreach ($this->maps as $map) {
            $map->clear();
        }
    }

    public function flushAllWriteBehind(): int
    {
        $total = 0;
        foreach ($this->maps as $map) {
            $total += $map->flushWriteBehind();
        }
        return $total;
    }

    public function getStats(): array
    {
        $mapStats = [];
        $totalEntries = 0;
        $totalL1Hits = 0;
        $totalL2Hits = 0;
        $totalMisses = 0;

        foreach ($this->maps as $name => $map) {
            $metrics = $map->getMetrics();
            $mapStats[$name] = $metrics;
            $totalEntries += $metrics['size'];
            $totalL1Hits += $metrics['l1NearCacheHits'];
            $totalL2Hits += $metrics['l2PartitionHits'];
            $totalMisses += $metrics['misses'];
        }

        $totalHits = $totalL1Hits + $totalL2Hits;
        $totalReqs = $totalHits + $totalMisses;
        $hitRate = $totalReqs > 0 ? ($totalHits / $totalReqs) * 100 : 0.0;

        $aliveNodes = count(array_filter($this->members, fn (MemberNode $n) => $n->isAlive()));

        return [
            'clusterName' => $this->clusterName,
            'localNode' => $this->localNode->toArray(),
            'totalMembers' => count($this->members),
            'aliveMembers' => $aliveNodes,
            'partitionCount' => $this->partitionMap->getPartitionCount(),
            'totalMaps' => count($this->maps),
            'totalEntries' => $totalEntries,
            'l1NearCacheHits' => $totalL1Hits,
            'l2PartitionHits' => $totalL2Hits,
            'misses' => $totalMisses,
            'hitRatePercent' => round($hitRate, 2),
            'maps' => $mapStats,
        ];
    }
}
