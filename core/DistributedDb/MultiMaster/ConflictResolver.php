<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\DistributedDb\MultiMaster;

/**
 * Deterministic multi-master conflict resolution engine.
 */
class ConflictResolver
{
    /**
     * Resolve a conflict between a local mutation and incoming remote mutation.
     *
     * @param ReplicationEvent $localEvent
     * @param ReplicationEvent $remoteEvent
     * @param ConflictResolutionStrategy $strategy
     * @param callable|null $customResolver fn(ReplicationEvent $local, ReplicationEvent $remote): ReplicationEvent
     * @return ReplicationEvent The winning or merged mutation event
     */
    public function resolve(
        ReplicationEvent $localEvent,
        ReplicationEvent $remoteEvent,
        ConflictResolutionStrategy $strategy = ConflictResolutionStrategy::LAST_WRITE_WINS,
        ?callable $customResolver = null
    ): ReplicationEvent {
        if ($strategy === ConflictResolutionStrategy::CUSTOM_CALLABLE && $customResolver !== null) {
            return $customResolver($localEvent, $remoteEvent);
        }

        if ($strategy === ConflictResolutionStrategy::VECTOR_CLOCK) {
            return $this->resolveByVectorClock($localEvent, $remoteEvent);
        }

        if ($strategy === ConflictResolutionStrategy::CRDT_MERGE) {
            return $this->resolveByCrdtMerge($localEvent, $remoteEvent);
        }

        // Default: Last-Write-Wins (LWW)
        return $this->resolveByLww($localEvent, $remoteEvent);
    }

    /**
     * Last-Write-Wins resolution with deterministic region tie-breaker.
     */
    public function resolveByLww(ReplicationEvent $local, ReplicationEvent $remote): ReplicationEvent
    {
        if ($remote->timestamp > $local->timestamp) {
            return $remote;
        }

        if ($remote->timestamp < $local->timestamp) {
            return $local;
        }

        // Equal timestamp: deterministic tie breaker on origin region name
        return (strcmp($remote->originRegion, $local->originRegion) > 0) ? $remote : $local;
    }

    /**
     * Vector Clock causality resolution.
     */
    public function resolveByVectorClock(ReplicationEvent $local, ReplicationEvent $remote): ReplicationEvent
    {
        $localDominates = $this->dominates($local->vectorClock, $remote->vectorClock);
        $remoteDominates = $this->dominates($remote->vectorClock, $local->vectorClock);

        if ($remoteDominates && !$localDominates) {
            return $remote;
        }

        if ($localDominates && !$remoteDominates) {
            return $local;
        }

        // Concurrent (neither dominates): merge vector clocks and tie-break using LWW
        $mergedClock = $this->mergeVectorClocks($local->vectorClock, $remote->vectorClock);
        $winner = $this->resolveByLww($local, $remote);

        return new ReplicationEvent(
            eventId: $winner->eventId,
            table: $winner->table,
            primaryKey: $winner->primaryKey,
            operation: $winner->operation,
            oldData: $winner->oldData,
            newData: $winner->newData,
            originRegion: $winner->originRegion,
            vectorClock: $mergedClock,
            timestamp: max($local->timestamp, $remote->timestamp),
            logicalClock: max($local->logicalClock, $remote->logicalClock) + 1
        );
    }

    /**
     * Column-level CRDT merge combining disjoint updated fields.
     */
    public function resolveByCrdtMerge(ReplicationEvent $local, ReplicationEvent $remote): ReplicationEvent
    {
        $mergedData = array_merge($local->newData, $remote->newData);
        $mergedClock = $this->mergeVectorClocks($local->vectorClock, $remote->vectorClock);

        return new ReplicationEvent(
            eventId: uniqid('merge_', true),
            table: $local->table,
            primaryKey: $local->primaryKey,
            operation: ($local->operation === 'DELETE' || $remote->operation === 'DELETE') ? 'DELETE' : 'UPDATE',
            oldData: $local->oldData,
            newData: $mergedData,
            originRegion: $local->originRegion . '+' . $remote->originRegion,
            vectorClock: $mergedClock,
            timestamp: max($local->timestamp, $remote->timestamp),
            logicalClock: max($local->logicalClock, $remote->logicalClock) + 1
        );
    }

    /**
     * Check if vector clock A strictly dominates vector clock B (A >= B with at least one >).
     */
    public function dominates(array $vcA, array $vcB): bool
    {
        $allKeys = array_unique(array_merge(array_keys($vcA), array_keys($vcB)));
        $hasStrictlyGreater = false;

        foreach ($allKeys as $k) {
            $valA = $vcA[$k] ?? 0;
            $valB = $vcB[$k] ?? 0;

            if ($valA < $valB) {
                return false;
            }

            if ($valA > $valB) {
                $hasStrictlyGreater = true;
            }
        }

        return $hasStrictlyGreater;
    }

    /**
     * Merge two vector clocks taking the maximum value for each region.
     */
    public function mergeVectorClocks(array $vcA, array $vcB): array
    {
        $merged = $vcA;
        foreach ($vcB as $region => $clock) {
            $merged[$region] = max($merged[$region] ?? 0, $clock);
        }

        return $merged;
    }
}
