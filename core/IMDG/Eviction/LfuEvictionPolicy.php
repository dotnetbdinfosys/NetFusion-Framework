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

namespace NetFusion\Core\IMDG\Eviction;

use NetFusion\Core\IMDG\Contracts\EvictionPolicyInterface;

class LfuEvictionPolicy implements EvictionPolicyInterface
{
    /** @var array<string, int> */
    protected array $counts = [];
    /** @var array<string, float> */
    protected array $lastAccess = [];

    public function recordAccess(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
        $this->lastAccess[$key] = microtime(true);
    }

    public function recordPut(string $key): void
    {
        if (!isset($this->counts[$key])) {
            $this->counts[$key] = 1;
        }
        $this->lastAccess[$key] = microtime(true);
    }

    public function recordRemove(string $key): void
    {
        unset($this->counts[$key], $this->lastAccess[$key]);
    }

    public function selectVictim(): ?string
    {
        if (empty($this->counts)) {
            return null;
        }

        $minKey = null;
        $minCount = PHP_INT_MAX;
        $oldestTime = INF;

        foreach ($this->counts as $key => $count) {
            $time = $this->lastAccess[$key] ?? 0.0;
            if ($count < $minCount || ($count === $minCount && $time < $oldestTime)) {
                $minCount = $count;
                $oldestTime = $time;
                $minKey = $key;
            }
        }

        return $minKey;
    }

    public function clear(): void
    {
        $this->counts = [];
        $this->lastAccess = [];
    }
}
