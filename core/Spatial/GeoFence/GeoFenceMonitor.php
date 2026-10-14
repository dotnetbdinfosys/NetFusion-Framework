<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Spatial\GeoFence;

use NetFusion\Core\Spatial\Enums\GeoFenceEvent;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Index\RTree;

/**
 * Real-Time Geo-Fence Event Monitor.
 * Evaluates moving spatial entities against geo-fence zones and triggers
 * ENTER, DWELL, and EXIT transition events.
 */
class GeoFenceMonitor
{
    /** @var array<string, GeoFence> [fenceId => GeoFence] */
    protected array $fences = [];

    /** @var array<string, array{insideFences: array<string, bool>, lastPoint: Point, lastTimestamp: int}> */
    protected array $entityStates = [];

    protected RTree $spatialIndex;

    /** @var array<callable> */
    protected array $eventListeners = [];

    public function __construct()
    {
        $this->spatialIndex = new RTree();
    }

    public function addFence(GeoFence $fence): self
    {
        $this->fences[$fence->getId()] = $fence;
        $this->spatialIndex->insert($fence->getBoundingBox(), $fence, $fence->getId());
        return $this;
    }

    public function removeFence(string $fenceId): self
    {
        if (isset($this->fences[$fenceId])) {
            unset($this->fences[$fenceId]);
            $this->spatialIndex->remove($fenceId);
        }
        return $this;
    }

    public function hasFence(string $fenceId): bool
    {
        return isset($this->fences[$fenceId]);
    }

    public function getFence(string $fenceId): ?GeoFence
    {
        return $this->fences[$fenceId] ?? null;
    }

    /**
     * @return array<string, GeoFence>
     */
    public function getFences(): array
    {
        return $this->fences;
    }

    /**
     * Process entity telemetry coordinate update.
     * Evaluates spatial inclusion and dispatches ENTER, DWELL, and EXIT events.
     *
     * @return array<int, array{entityId: string, fence: GeoFence, event: GeoFenceEvent, point: Point, timestamp: int}>
     */
    public function updatePosition(string $entityId, Point $point, ?int $timestamp = null): array
    {
        $ts = $timestamp ?? time();
        $previousState = $this->entityStates[$entityId] ?? [
            'insideFences' => [],
            'lastPoint' => $point,
            'lastTimestamp' => $ts,
        ];

        $previouslyInside = $previousState['insideFences'];
        $currentlyInside = [];
        $events = [];

        // Fast R-tree query for candidate fences intersecting point
        $candidateBoxes = $this->spatialIndex->knn($point, k: max(1, count($this->fences)));
        foreach ($candidateBoxes as $cand) {
            $fence = $cand['data'];
            if ($fence instanceof GeoFence && $fence->containsPoint($point)) {
                $currentlyInside[$fence->getId()] = true;

                if (!isset($previouslyInside[$fence->getId()])) {
                    // Was outside, now inside -> ENTER
                    $events[] = [
                        'entityId' => $entityId,
                        'fence' => $fence,
                        'event' => GeoFenceEvent::ENTER,
                        'point' => $point,
                        'timestamp' => $ts,
                    ];
                } else {
                    // Was inside, still inside -> DWELL
                    $events[] = [
                        'entityId' => $entityId,
                        'fence' => $fence,
                        'event' => GeoFenceEvent::DWELL,
                        'point' => $point,
                        'timestamp' => $ts,
                    ];
                }
            }
        }

        // Check for fences the entity has exited
        foreach ($previouslyInside as $fenceId => $trueVal) {
            if (!isset($currentlyInside[$fenceId]) && isset($this->fences[$fenceId])) {
                $events[] = [
                    'entityId' => $entityId,
                    'fence' => $this->fences[$fenceId],
                    'event' => GeoFenceEvent::EXIT,
                    'point' => $point,
                    'timestamp' => $ts,
                ];
            }
        }

        // Save updated entity state
        $this->entityStates[$entityId] = [
            'insideFences' => $currentlyInside,
            'lastPoint' => $point,
            'lastTimestamp' => $ts,
        ];

        // Notify event listeners
        foreach ($events as $event) {
            foreach ($this->eventListeners as $listener) {
                $listener($event['entityId'], $event['fence'], $event['event'], $event['point'], $event['timestamp']);
            }
        }

        return $events;
    }

    public function onEvent(callable $callback): self
    {
        $this->eventListeners[] = $callback;
        return $this;
    }

    /**
     * @return array{insideFences: array<string, bool>, lastPoint: Point, lastTimestamp: int}|null
     */
    public function getEntityState(string $entityId): ?array
    {
        return $this->entityStates[$entityId] ?? null;
    }
}
