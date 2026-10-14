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

class FifoEvictionPolicy implements EvictionPolicyInterface
{
    /** @var array<string, float> */
    protected array $insertionTimes = [];

    public function recordAccess(string $key): void
    {
        // FIFO ignores read access order
    }

    public function recordPut(string $key): void
    {
        if (!isset($this->insertionTimes[$key])) {
            $this->insertionTimes[$key] = microtime(true);
        }
    }

    public function recordRemove(string $key): void
    {
        unset($this->insertionTimes[$key]);
    }

    public function selectVictim(): ?string
    {
        if (empty($this->insertionTimes)) {
            return null;
        }

        $oldestKey = null;
        $oldestTime = INF;

        foreach ($this->insertionTimes as $key => $time) {
            if ($time < $oldestTime) {
                $oldestTime = $time;
                $oldestKey = $key;
            }
        }

        return $oldestKey;
    }

    public function clear(): void
    {
        $this->insertionTimes = [];
    }
}
