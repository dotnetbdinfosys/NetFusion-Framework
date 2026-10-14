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

namespace NetFusion\Core\IMDG\NearCache;

use NetFusion\Core\IMDG\Contracts\EvictionPolicyInterface;
use NetFusion\Core\IMDG\Eviction\FifoEvictionPolicy;
use NetFusion\Core\IMDG\Eviction\LfuEvictionPolicy;
use NetFusion\Core\IMDG\Eviction\LruEvictionPolicy;

class NearCache
{
    protected string $mapName;
    protected NearCacheConfig $config;
    protected EvictionPolicyInterface $evictionPolicy;

    /** @var array<string, mixed> */
    protected array $entries = [];
    /** @var array<string, float> */
    protected array $creationTimes = [];
    /** @var array<string, float> */
    protected array $lastAccessTimes = [];

    protected int $hits = 0;
    protected int $misses = 0;
    protected int $evictions = 0;
    protected int $invalidations = 0;

    public function __construct(string $mapName, ?NearCacheConfig $config = null)
    {
        $this->mapName = $mapName;
        $this->config = $config ?? new NearCacheConfig();
        $this->evictionPolicy = match ($this->config->getEvictionPolicy()) {
            'lfu' => new LfuEvictionPolicy(),
            'fifo' => new FifoEvictionPolicy(),
            default => new LruEvictionPolicy()
        };
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->entries)) {
            $this->misses++;
            return null;
        }

        $now = microtime(true);

        // Check TTL expiration
        if ($this->config->getTtlSeconds() > 0) {
            $created = $this->creationTimes[$key] ?? $now;
            if (($now - $created) > $this->config->getTtlSeconds()) {
                $this->removeEntry($key);
                $this->misses++;
                return null;
            }
        }

        // Check TTI (idle time) expiration
        if ($this->config->getTtiSeconds() > 0) {
            $lastAccess = $this->lastAccessTimes[$key] ?? $now;
            if (($now - $lastAccess) > $this->config->getTtiSeconds()) {
                $this->removeEntry($key);
                $this->misses++;
                return null;
            }
        }

        $this->lastAccessTimes[$key] = $now;
        $this->evictionPolicy->recordAccess($key);
        $this->hits++;

        return $this->entries[$key];
    }

    public function put(string $key, mixed $value): void
    {
        // If key not present and reached maxSize, trigger eviction
        if (!array_key_exists($key, $this->entries) && count($this->entries) >= $this->config->getMaxSize()) {
            $victim = $this->evictionPolicy->selectVictim();
            if ($victim !== null) {
                $this->removeEntry($victim);
                $this->evictions++;
            }
        }

        $now = microtime(true);
        $this->entries[$key] = $value;
        $this->creationTimes[$key] = $now;
        $this->lastAccessTimes[$key] = $now;
        $this->evictionPolicy->recordPut($key);
    }

    public function invalidate(string $key): void
    {
        if (array_key_exists($key, $this->entries)) {
            $this->removeEntry($key);
            $this->invalidations++;
        }
    }

    protected function removeEntry(string $key): void
    {
        unset($this->entries[$key], $this->creationTimes[$key], $this->lastAccessTimes[$key]);
        $this->evictionPolicy->recordRemove($key);
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->creationTimes = [];
        $this->lastAccessTimes = [];
        $this->evictionPolicy->clear();
    }

    public function size(): int
    {
        return count($this->entries);
    }

    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0.0;

        return [
            'mapName' => $this->mapName,
            'size' => count($this->entries),
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hitRatePercent' => round($hitRate, 2),
            'evictions' => $this->evictions,
            'invalidations' => $this->invalidations,
        ];
    }
}
