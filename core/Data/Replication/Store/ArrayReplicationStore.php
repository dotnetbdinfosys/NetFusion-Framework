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

namespace NetFusion\Core\Data\Replication\Store;

use NetFusion\Core\Data\Replication\ReplicationEvent;

class ArrayReplicationStore
{
    /** @var ReplicationEvent[] */
    protected array $events = [];

    public function record(ReplicationEvent $event): bool
    {
        $this->events[$event->id] = $event;
        return true;
    }

    /**
     * @return ReplicationEvent[]
     */
    public function getPending(?int $limit = null): array
    {
        $pending = [];
        foreach ($this->events as $evt) {
            if ($evt->status === 'pending') {
                $pending[] = $evt;
                if ($limit !== null && count($pending) >= $limit) {
                    break;
                }
            }
        }
        return $pending;
    }

    /**
     * @return ReplicationEvent[]
     */
    public function getSince(float $timestamp): array
    {
        $matches = [];
        foreach ($this->events as $evt) {
            if ($evt->timestamp >= $timestamp) {
                $matches[] = $evt;
            }
        }
        return $matches;
    }

    public function markSynced(string $eventId): bool
    {
        if (isset($this->events[$eventId])) {
            $this->events[$eventId]->status = 'synced';
            return true;
        }
        return false;
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
