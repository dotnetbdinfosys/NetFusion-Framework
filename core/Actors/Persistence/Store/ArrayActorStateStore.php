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

namespace NetFusion\Core\Actors\Persistence\Store;

use NetFusion\Core\Actors\Persistence\Contracts\ActorStateStoreInterface;

class ArrayActorStateStore implements ActorStateStoreInterface
{
    /** @var array<string, array{sequence: int, state: mixed, timestamp: float}> */
    protected array $snapshots = [];

    /** @var array<string, list<array{sequence: int, event: mixed, timestamp: float}>> */
    protected array $journals = [];

    public function saveSnapshot(string $persistenceId, int $sequenceNumber, mixed $state): bool
    {
        $this->snapshots[$persistenceId] = [
            'sequence' => $sequenceNumber,
            'state' => $state,
            'timestamp' => microtime(true),
        ];
        return true;
    }

    public function loadSnapshot(string $persistenceId): ?array
    {
        return $this->snapshots[$persistenceId] ?? null;
    }

    public function appendEvent(string $persistenceId, int $sequenceNumber, mixed $event): bool
    {
        $this->journals[$persistenceId][] = [
            'sequence' => $sequenceNumber,
            'event' => $event,
            'timestamp' => microtime(true),
        ];
        return true;
    }

    public function getEvents(string $persistenceId, int $fromSequence = 1): array
    {
        $all = $this->journals[$persistenceId] ?? [];
        return array_values(array_filter($all, fn($e) => $e['sequence'] >= $fromSequence));
    }

    public function purge(string $persistenceId): bool
    {
        unset($this->snapshots[$persistenceId], $this->journals[$persistenceId]);
        return true;
    }
}
