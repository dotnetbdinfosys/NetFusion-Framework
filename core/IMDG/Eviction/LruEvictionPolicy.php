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

class LruEvictionPolicy implements EvictionPolicyInterface
{
    /** @var array<string, float> */
    protected array $accessTimes = [];

    public function recordAccess(string $key): void
    {
        $this->accessTimes[$key] = microtime(true);
    }

    public function recordPut(string $key): void
    {
        $this->accessTimes[$key] = microtime(true);
    }

    public function recordRemove(string $key): void
    {
        unset($this->accessTimes[$key]);
    }

    public function selectVictim(): ?string
    {
        if (empty($this->accessTimes)) {
            return null;
        }

        $oldestKey = null;
        $oldestTime = INF;

        foreach ($this->accessTimes as $key => $time) {
            if ($time < $oldestTime) {
                $oldestTime = $time;
                $oldestKey = $key;
            }
        }

        return $oldestKey;
    }

    public function clear(): void
    {
        $this->accessTimes = [];
    }
}
