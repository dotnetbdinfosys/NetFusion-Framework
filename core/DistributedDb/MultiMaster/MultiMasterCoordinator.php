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
 * Multi-master coordinator managing cross-region synchronization and conflict resolution.
 */
class MultiMasterCoordinator
{
    /**
     * @var array<string, GeoRegionNode>
     */
    private array $regions = [];

    private ConflictResolver $conflictResolver;
    private ReplicationMode $replicationMode = ReplicationMode::ASYNC;
    private ConflictResolutionStrategy $conflictStrategy = ConflictResolutionStrategy::LAST_WRITE_WINS;

    /**
     * @var callable|null
     */
    private $customResolver = null;

    public function __construct(
        array $regions = [],
        ReplicationMode $mode = ReplicationMode::ASYNC,
        ConflictResolutionStrategy $strategy = ConflictResolutionStrategy::LAST_WRITE_WINS
    ) {
        $this->conflictResolver = new ConflictResolver();
        $this->replicationMode = $mode;
        $this->conflictStrategy = $strategy;

        foreach ($regions as $region) {
            $this->addRegion($region);
        }
    }

    public function addRegion(GeoRegionNode $region): self
    {
        $this->regions[$region->getRegionId()] = $region;
        return $this;
    }

    public function getRegion(string $regionId): ?GeoRegionNode
    {
        return $this->regions[$regionId] ?? null;
    }

    /**
     * @return array<string, GeoRegionNode>
     */
    public function getRegions(): array
    {
        return $this->regions;
    }

    public function setReplicationMode(ReplicationMode $mode): self
    {
        $this->replicationMode = $mode;
        return $this;
    }

    public function getReplicationMode(): ReplicationMode
    {
        return $this->replicationMode;
    }

    public function setConflictStrategy(ConflictResolutionStrategy $strategy, ?callable $customResolver = null): self
    {
        $this->conflictStrategy = $strategy;
        $this->customResolver = $customResolver;
        return $this;
    }

    public function getConflictStrategy(): ConflictResolutionStrategy
    {
        return $this->conflictStrategy;
    }

    public function getQuorum(): int
    {
        $n = count($this->regions);
        return ($n === 0) ? 1 : (int) (floor($n / 2) + 1);
    }

    /**
     * Execute a write mutation on a specific origin region.
     */
    public function write(
        string $originRegion,
        string $table,
        mixed $pk,
        array $data,
        string $op = 'UPDATE'
    ): ReplicationEvent {
        if (!isset($this->regions[$originRegion])) {
            throw new \InvalidArgumentException("Region '{$originRegion}' is not registered in multi-master coordinator.");
        }

        $origin = $this->regions[$originRegion];
        $allRegionIds = array_keys($this->regions);
        $event = $origin->write($table, $pk, $data, $op, $allRegionIds);

        // Synchronous replication modes
        if ($this->replicationMode === ReplicationMode::SYNC_QUORUM) {
            $quorum = $this->getQuorum();
            $ackCount = 1; // Origin itself

            foreach ($this->regions as $peerId => $peer) {
                if ($peerId === $originRegion) {
                    continue;
                }

                $events = $origin->drainOutboxFor($peerId);
                foreach ($events as $evt) {
                    $peer->applyRemoteEvent($evt, $this->conflictResolver, $this->conflictStrategy, $this->customResolver);
                }
                $ackCount++;
                if ($ackCount >= $quorum) {
                    break;
                }
            }
        } elseif ($this->replicationMode === ReplicationMode::SEMI_SYNC) {
            // Replicate to at least one peer synchronously
            foreach ($this->regions as $peerId => $peer) {
                if ($peerId === $originRegion) {
                    continue;
                }

                $events = $origin->drainOutboxFor($peerId);
                foreach ($events as $evt) {
                    $peer->applyRemoteEvent($evt, $this->conflictResolver, $this->conflictStrategy, $this->customResolver);
                }
                break;
            }
        }

        return $event;
    }

    /**
     * Flush and synchronize replication streams between geo-regions.
     *
     * @param string|null $sourceRegion Specific source or null for all
     * @param string|null $targetRegion Specific target or null for all
     * @return int Total number of events synchronized
     */
    public function synchronize(?string $sourceRegion = null, ?string $targetRegion = null): int
    {
        $syncedCount = 0;
        $sources = $sourceRegion ? [$sourceRegion => $this->regions[$sourceRegion] ?? null] : $this->regions;

        foreach ($sources as $srcId => $srcNode) {
            if ($srcNode === null) {
                continue;
            }

            $targets = $targetRegion ? [$targetRegion => $this->regions[$targetRegion] ?? null] : $this->regions;
            foreach ($targets as $dstId => $dstNode) {
                if ($dstNode === null || $srcId === $dstId) {
                    continue;
                }

                $events = $srcNode->drainOutboxFor($dstId);
                foreach ($events as $event) {
                    $dstNode->applyRemoteEvent(
                        $event,
                        $this->conflictResolver,
                        $this->conflictStrategy,
                        $this->customResolver
                    );
                    $syncedCount++;
                }
            }
        }

        return $syncedCount;
    }

    /**
     * Get aggregate conflict history from all regions.
     */
    public function getAllConflictHistory(): array
    {
        $history = [];
        foreach ($this->regions as $regionId => $node) {
            foreach ($node->getConflictHistory() as $conflict) {
                $conflict['reporting_region'] = $regionId;
                $history[] = $conflict;
            }
        }

        return $history;
    }

    /**
     * Get replication lag / pending outbox queue depth per region.
     */
    public function getReplicationLag(): array
    {
        $lag = [];
        foreach ($this->regions as $regionId => $node) {
            $lag[$regionId] = [
                'pending_outbox' => $node->getPendingOutboxCount(),
                'vector_clock' => $node->getVectorClock(),
            ];
        }

        return $lag;
    }
}
