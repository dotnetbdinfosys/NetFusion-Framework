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

use NetFusion\Core\IMDG\Contracts\ContinuousQueryListenerInterface;
use NetFusion\Core\IMDG\Contracts\DistributedMapInterface;
use NetFusion\Core\IMDG\Contracts\EntryProcessorInterface;
use NetFusion\Core\IMDG\Contracts\Store\GridPersistenceStoreInterface;
use NetFusion\Core\IMDG\ContinuousQuery\ContinuousQueryManager;
use NetFusion\Core\IMDG\ContinuousQuery\EntryEvent;
use NetFusion\Core\IMDG\NearCache\NearCache;
use NetFusion\Core\IMDG\Partitioning\PartitionMap;
use NetFusion\Core\IMDG\Vector\GridVectorIndex;
use NetFusion\Core\IMDG\Exceptions\EntryProcessorException;
use Throwable;

class DistributedMap implements DistributedMapInterface
{
    protected string $name;
    protected PartitionMap $partitionMap;
    protected ?NearCache $nearCache;
    protected ?GridPersistenceStoreInterface $persistenceStore;
    protected string $writeMode;
    /** @var array<string, array{value: mixed, ttl: int, deleted: bool}> */
    protected array $writeBehindBuffer = [];
    protected ContinuousQueryManager $continuousQueries;
    protected ?GridVectorIndex $vectorIndex = null;

    protected int $l1Hits = 0;
    protected int $l2Hits = 0;
    protected int $misses = 0;
    protected int $puts = 0;
    protected int $removes = 0;

    public function __construct(
        string $name,
        PartitionMap $partitionMap,
        ?NearCache $nearCache = null,
        ?GridPersistenceStoreInterface $persistenceStore = null,
        string $writeMode = 'none'
    ) {
        $this->name = $name;
        $this->partitionMap = $partitionMap;
        $this->nearCache = $nearCache;
        $this->persistenceStore = $persistenceStore;
        $this->writeMode = strtolower($writeMode);
        $this->continuousQueries = new ContinuousQueryManager();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNearCache(): ?NearCache
    {
        return $this->nearCache;
    }

    public function setNearCache(?NearCache $nearCache): self
    {
        $this->nearCache = $nearCache;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // 1. Tier 1: L1 Near-Cache Lookup
        if ($this->nearCache !== null) {
            $l1Val = $this->nearCache->get($key);
            if ($l1Val !== null) {
                $this->l1Hits++;
                return $l1Val;
            }
        }

        // 2. Tier 2: L2 Partition Ring Lookup
        $partition = $this->partitionMap->getPartitionForKey($key);
        $val = $partition->get($this->name, $key);

        if ($val !== null) {
            $this->l2Hits++;
            if ($this->nearCache !== null) {
                $this->nearCache->put($key, $val);
            }
            return $val;
        }

        // 3. Read-Through from Persistence Store if configured
        if ($this->persistenceStore !== null) {
            $storeVal = $this->persistenceStore->load($this->name, $key);
            if ($storeVal !== null) {
                $partition->put($this->name, $key, $storeVal);
                if ($this->nearCache !== null) {
                    $this->nearCache->put($key, $storeVal);
                }
                $this->l2Hits++;
                return $storeVal;
            }
        }

        $this->misses++;
        return $default;
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 0): mixed
    {
        $partition = $this->partitionMap->getPartitionForKey($key);
        $prevVal = $partition->get($this->name, $key);
        $partition->put($this->name, $key, $value, $ttlSeconds);

        // Update / Invalidate L1 Near-Cache
        if ($this->nearCache !== null) {
            if ($this->nearCache->get($key) !== null) {
                $this->nearCache->put($key, $value);
            }
        }

        // Persistence handling
        if ($this->persistenceStore !== null) {
            if ($this->writeMode === 'write-through') {
                $this->persistenceStore->store($this->name, $key, $value, $ttlSeconds);
            } elseif ($this->writeMode === 'write-behind') {
                $this->writeBehindBuffer[$key] = [
                    'value' => $value,
                    'ttl' => $ttlSeconds,
                    'deleted' => false,
                ];
            }
        }

        $this->puts++;

        // Dispatch continuous query event
        $eventType = ($prevVal === null) ? EntryEvent::TYPE_ADDED : EntryEvent::TYPE_UPDATED;
        $this->continuousQueries->dispatch(new EntryEvent($this->name, $key, $value, $prevVal, $eventType));

        return $prevVal;
    }

    public function putIfAbsent(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        if ($this->containsKey($key)) {
            return false;
        }

        $this->put($key, $value, $ttlSeconds);
        return true;
    }

    public function remove(string $key): mixed
    {
        $partition = $this->partitionMap->getPartitionForKey($key);
        $prevVal = $partition->remove($this->name, $key);

        if ($this->nearCache !== null) {
            $this->nearCache->invalidate($key);
        }

        if ($this->persistenceStore !== null) {
            if ($this->writeMode === 'write-through') {
                $this->persistenceStore->delete($this->name, $key);
            } elseif ($this->writeMode === 'write-behind') {
                $this->writeBehindBuffer[$key] = [
                    'value' => null,
                    'ttl' => 0,
                    'deleted' => true,
                ];
            }
        }

        if ($this->vectorIndex !== null) {
            $this->vectorIndex->remove($key);
        }

        $this->removes++;

        if ($prevVal !== null) {
            $this->continuousQueries->dispatch(new EntryEvent($this->name, $key, null, $prevVal, EntryEvent::TYPE_REMOVED));
        }

        return $prevVal;
    }

    public function containsKey(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function containsValue(mixed $value): bool
    {
        foreach ($this->values() as $v) {
            if ($v === $value) {
                return true;
            }
        }
        return false;
    }

    public function executeOnKey(string $key, EntryProcessorInterface $processor): mixed
    {
        $partition = $this->partitionMap->getPartitionForKey($key);
        $val = $partition->get($this->name, $key);
        $prevVal = $val;

        try {
            $result = $processor->process($key, $val);
        } catch (Throwable $e) {
            throw new EntryProcessorException($key, $e->getMessage(), $e);
        }

        if ($val === null && $prevVal !== null) {
            $this->remove($key);
        } else {
            $partition->put($this->name, $key, $val);

            if ($this->nearCache !== null) {
                $this->nearCache->put($key, $val);
            }

            if ($this->persistenceStore !== null && $this->writeMode === 'write-through') {
                $this->persistenceStore->store($this->name, $key, $val);
            }

            $this->continuousQueries->dispatch(new EntryEvent($this->name, $key, $val, $prevVal, EntryEvent::TYPE_UPDATED));
        }

        return $result;
    }

    public function executeOnEntries(EntryProcessorInterface $processor): array
    {
        $results = [];
        foreach ($this->keySet() as $key) {
            $results[$key] = $this->executeOnKey($key, $processor);
        }
        return $results;
    }

    public function addEntryListener(ContinuousQueryListenerInterface|callable $listener, ?callable $filter = null): string
    {
        return $this->continuousQueries->addListener($listener, $filter);
    }

    public function removeEntryListener(string $listenerId): bool
    {
        return $this->continuousQueries->removeListener($listenerId);
    }

    public function keySet(): array
    {
        $keys = [];
        foreach ($this->partitionMap->getAllPartitions() as $partition) {
            foreach ($partition->keySet($this->name) as $k) {
                $keys[] = $k;
            }
        }
        return array_unique($keys);
    }

    public function values(): array
    {
        $values = [];
        foreach ($this->partitionMap->getAllPartitions() as $partition) {
            foreach ($partition->values($this->name) as $v) {
                $values[] = $v;
            }
        }
        return $values;
    }

    public function size(): int
    {
        $count = 0;
        foreach ($this->partitionMap->getAllPartitions() as $partition) {
            $count += $partition->size($this->name);
        }
        return $count;
    }

    public function clear(): void
    {
        foreach ($this->partitionMap->getAllPartitions() as $partition) {
            $partition->clear($this->name);
        }

        if ($this->nearCache !== null) {
            $this->nearCache->clear();
        }

        if ($this->persistenceStore !== null && $this->writeMode === 'write-through') {
            $this->persistenceStore->deleteAll($this->name);
        }

        if ($this->vectorIndex !== null) {
            $this->vectorIndex->clear();
        }

        $this->writeBehindBuffer = [];
    }

    public function flushWriteBehind(): int
    {
        if ($this->persistenceStore === null || empty($this->writeBehindBuffer)) {
            return 0;
        }

        $count = 0;
        foreach ($this->writeBehindBuffer as $key => $item) {
            if ($item['deleted']) {
                $this->persistenceStore->delete($this->name, $key);
            } else {
                $this->persistenceStore->store($this->name, $key, $item['value'], $item['ttl']);
            }
            $count++;
        }

        $this->writeBehindBuffer = [];
        return $count;
    }

    public function vectorIndex(string $metric = 'cosine'): GridVectorIndex
    {
        if ($this->vectorIndex === null) {
            $this->vectorIndex = new GridVectorIndex($metric);
        }
        return $this->vectorIndex;
    }

    public function putVector(string $key, mixed $value, array $vector, array $metadata = []): mixed
    {
        $res = $this->put($key, $value);
        $this->vectorIndex()->index($key, $vector, $metadata);
        return $res;
    }

    public function searchVector(array $queryVector, int $topK = 5, float $minScore = 0.0): array
    {
        if ($this->vectorIndex === null) {
            return [];
        }

        $results = $this->vectorIndex->search($queryVector, $topK, $minScore);
        $hydrated = [];

        foreach ($results as $res) {
            $key = $res['key'];
            $hydrated[] = [
                'key' => $key,
                'score' => $res['score'],
                'value' => $this->get($key),
                'metadata' => $res['metadata'],
            ];
        }

        return $hydrated;
    }

    public function getMetrics(): array
    {
        $totalHits = $this->l1Hits + $this->l2Hits;
        $totalReqs = $totalHits + $this->misses;
        $hitRate = $totalReqs > 0 ? ($totalHits / $totalReqs) * 100 : 0.0;

        return [
            'mapName' => $this->name,
            'size' => $this->size(),
            'l1NearCacheHits' => $this->l1Hits,
            'l2PartitionHits' => $this->l2Hits,
            'misses' => $this->misses,
            'puts' => $this->puts,
            'removes' => $this->removes,
            'hitRatePercent' => round($hitRate, 2),
            'writeMode' => $this->writeMode,
            'writeBehindPending' => count($this->writeBehindBuffer),
            'nearCache' => $this->nearCache?->getStats() ?? null,
            'vectorIndexSize' => $this->vectorIndex?->size() ?? 0,
        ];
    }
}
