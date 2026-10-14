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

namespace NetFusion\Core\Actors\Persistence\Contracts;

interface ActorStateStoreInterface
{
    /**
     * Save actor state snapshot.
     *
     * @param string $persistenceId
     * @param int    $sequenceNumber
     * @param mixed  $state
     * @return bool
     */
    public function saveSnapshot(string $persistenceId, int $sequenceNumber, mixed $state): bool;

    /**
     * Load latest actor snapshot.
     *
     * @param string $persistenceId
     * @return array{sequence: int, state: mixed, timestamp: float}|null
     */
    public function loadSnapshot(string $persistenceId): ?array;

    /**
     * Append journal event for actor.
     *
     * @param string $persistenceId
     * @param int    $sequenceNumber
     * @param mixed  $event
     * @return bool
     */
    public function appendEvent(string $persistenceId, int $sequenceNumber, mixed $event): bool;

    /**
     * Read events stream for actor starting from sequence.
     *
     * @param string $persistenceId
     * @param int    $fromSequence
     * @return list<array{sequence: int, event: mixed, timestamp: float}>
     */
    public function getEvents(string $persistenceId, int $fromSequence = 1): array;

    /**
     * Delete snapshots and events for an actor.
     */
    public function purge(string $persistenceId): bool;
}
