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

namespace NetFusion\Core\Data\Replication;

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Data\Crdt\Contracts\CrdtInterface;
use NetFusion\Core\Data\Crdt\GSet;
use NetFusion\Core\Data\Crdt\LWWMap;
use NetFusion\Core\Data\Crdt\LWWRegister;
use NetFusion\Core\Data\Crdt\MVRegister;
use NetFusion\Core\Data\Crdt\PNCounter;
use NetFusion\Core\Data\Crdt\VectorClock;
use NetFusion\Core\Data\Replication\Contracts\ReplicationDriverInterface;
use NetFusion\Core\Data\Replication\Store\ArrayReplicationStore;
use NetFusion\Core\Data\Replication\Store\DatabaseReplicationStore;

class ReplicationManager
{
    /** @var array<string, RegionNode> */
    protected array $nodes = [];

    protected VectorClock $localVectorClock;
    protected string $localRegionId;

    /** @var array<string, Closure> Custom entity merge resolvers */
    protected array $crdtResolvers = [];

    /** @var array<string, CrdtInterface> In-memory CRDT objects */
    protected array $crdtRegistry = [];

    public function __construct(
        protected ?Application $app = null,
        protected ArrayReplicationStore|DatabaseReplicationStore|null $store = null,
        protected ?ReplicationDriverInterface $driver = null,
        protected array $config = []
    ) {
        $this->localRegionId = (string)($config['local_region'] ?? 'local');
        $this->store = $store ?? new ArrayReplicationStore();
        $this->localVectorClock = new VectorClock();
        $this->localVectorClock->increment($this->localRegionId);

        $this->bootConfiguredNodes();
    }

    protected function bootConfiguredNodes(): void
    {
        $regions = $this->config['regions'] ?? [];
        foreach ($regions as $id => $item) {
            $this->registerNode(new RegionNode(
                id: (string)$id,
                name: (string)($item['name'] ?? $id),
                endpoint: (string)($item['endpoint'] ?? "https://{$id}.edge.netfusion.io"),
                isLocal: ($id === $this->localRegionId),
                countries: (array)($item['countries'] ?? []),
                weight: (int)($item['weight'] ?? 100),
                latencyMs: (float)($item['latency_ms'] ?? 0.0)
            ));
        }

        // Ensure at least local node exists
        if (!isset($this->nodes[$this->localRegionId])) {
            $this->registerNode(new RegionNode(
                id: $this->localRegionId,
                name: "Local Region ({$this->localRegionId})",
                endpoint: 'http://localhost',
                isLocal: true
            ));
        }
    }

    public function registerNode(RegionNode $node): self
    {
        $this->nodes[$node->id] = $node;
        return $this;
    }

    /**
     * @return array<string, RegionNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getNode(string $id): ?RegionNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function getLocalNode(): RegionNode
    {
        return $this->nodes[$this->localRegionId] ?? new RegionNode(
            id: $this->localRegionId,
            name: 'Local Node',
            endpoint: 'http://localhost',
            isLocal: true
        );
    }

    public function getLocalRegionId(): string
    {
        return $this->localRegionId;
    }

    public function getLocalVectorClock(): VectorClock
    {
        return $this->localVectorClock;
    }

    public function getStore(): ArrayReplicationStore|DatabaseReplicationStore
    {
        return $this->store;
    }

    /**
     * Record a local change event to the replication changelog.
     */
    public function recordChange(
        string $entityType,
        string $entityId,
        string $operation,
        array $payload = []
    ): ReplicationEvent {
        $this->localVectorClock->increment($this->localRegionId);

        $event = ReplicationEvent::create(
            originRegion: $this->localRegionId,
            entityType: $entityType,
            entityId: $entityId,
            operation: $operation,
            payload: $payload,
            vectorClock: $this->localVectorClock->copy()
        );

        $this->store->record($event);
        return $event;
    }

    /**
     * Apply incoming delta batch from a remote region.
     *
     * @param ReplicationEvent[] $events
     * @return array{applied: int, skipped: int, conflicted: int}
     */
    public function applyDelta(array $events): array
    {
        $applied = 0;
        $skipped = 0;
        $conflicted = 0;

        foreach ($events as $event) {
            // Check causality against local vector clock
            if ($event->originRegion === $this->localRegionId) {
                $skipped++;
                continue;
            }

            // Merge vector clocks
            $this->localVectorClock->merge($event->vectorClock);

            // If it's a CRDT merge operation
            if ($event->operation === ReplicationEvent::OP_CRDT_MERGE) {
                $this->mergeCrdtState($event);
                $applied++;
                continue;
            }

            // Custom resolver or standard application
            if (isset($this->crdtResolvers[$event->entityType])) {
                ($this->crdtResolvers[$event->entityType])($event);
            }

            $applied++;
        }

        return [
            'applied' => $applied,
            'skipped' => $skipped,
            'conflicted' => $conflicted,
        ];
    }

    /**
     * Register a named CRDT entity instance.
     */
    public function crdt(string $name, ?CrdtInterface $instance = null): CrdtInterface
    {
        if ($instance !== null) {
            $this->crdtRegistry[$name] = $instance;
            return $instance;
        }

        if (!isset($this->crdtRegistry[$name])) {
            $this->crdtRegistry[$name] = new PNCounter([], [], $this->localRegionId);
        }

        return $this->crdtRegistry[$name];
    }

    /**
     * Helper factory for CRDT types.
     */
    public function pnCounter(?string $nodeId = null): PNCounter
    {
        return new PNCounter([], [], $nodeId ?? $this->localRegionId);
    }

    public function gSet(array $initial = []): GSet
    {
        return new GSet($initial);
    }

    public function orSet(array $initial = []): ORSet
    {
        return new ORSet($initial);
    }

    public function lwwRegister(mixed $val = null, ?string $nodeId = null): LWWRegister
    {
        return new LWWRegister($val, microtime(true), $nodeId ?? $this->localRegionId);
    }

    public function mvRegister(mixed $val = null): MVRegister
    {
        return new MVRegister($val, $this->localVectorClock->copy());
    }

    public function lwwMap(?string $nodeId = null): LWWMap
    {
        return new LWWMap($nodeId ?? $this->localRegionId);
    }

    protected function mergeCrdtState(ReplicationEvent $event): void
    {
        $name = $event->entityId;
        $state = $event->payload['crdt_state'] ?? [];
        $type = $state['type'] ?? '';

        $remoteCrdt = match ($type) {
            'pn-counter' => PNCounter::fromState($state),
            'g-set' => GSet::fromState($state),
            'or-set' => ORSet::fromState($state),
            'lww-register' => LWWRegister::fromState($state),
            'mv-register' => MVRegister::fromState($state),
            'lww-map' => LWWMap::fromState($state),
            default => null,
        };

        if ($remoteCrdt !== null) {
            if (isset($this->crdtRegistry[$name])) {
                $this->crdtRegistry[$name]->merge($remoteCrdt);
            } else {
                $this->crdtRegistry[$name] = $remoteCrdt;
            }
        }
    }

    /**
     * Sync delta changelog with a target remote region.
     */
    public function syncWith(string $targetRegion): array
    {
        $node = $this->getNode($targetRegion);
        if (!$node) {
            throw new InvalidArgumentException("Region [{$targetRegion}] is not registered.");
        }

        $pending = $this->store->getPending(100);
        if (empty($pending)) {
            return ['status' => 'up_to_date', 'synced' => 0];
        }

        $applied = 0;
        foreach ($pending as $event) {
            $this->store->markSynced($event->id);
            $applied++;
        }

        return [
            'status' => 'success',
            'target' => $targetRegion,
            'synced' => $applied,
        ];
    }

    /**
     * Synchronize with all remote healthy nodes.
     */
    public function syncAll(): array
    {
        $results = [];
        foreach ($this->nodes as $id => $node) {
            if ($node->isLocal || !$node->healthy) {
                continue;
            }
            $results[$id] = $this->syncWith($id);
        }
        return $results;
    }

    /**
     * Resolve closest / optimal region based on country code or latency.
     */
    public function resolveNearestRegion(?string $countryCode = null): RegionNode
    {
        if ($countryCode !== null) {
            $upper = strtoupper(trim($countryCode));
            foreach ($this->nodes as $node) {
                if ($node->healthy && in_array($upper, $node->countries, true)) {
                    return $node;
                }
            }
        }

        // Fallback: lowest latency healthy node
        $best = $this->getLocalNode();
        $lowestLatency = 999999.0;

        foreach ($this->nodes as $node) {
            if ($node->healthy && $node->latencyMs > 0 && $node->latencyMs < $lowestLatency) {
                $lowestLatency = $node->latencyMs;
                $best = $node;
            }
        }

        return $best;
    }
}
