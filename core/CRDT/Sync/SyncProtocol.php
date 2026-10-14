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

namespace NetFusion\Core\CRDT\Sync;

use NetFusion\Core\CRDT\Types\CrdtInterface;
use NetFusion\Core\CRDT\Causality\VectorClock;
use NetFusion\Core\CRDT\Enums\SyncMode;

/**
 * Sync protocol handler orchestrating peer handshake, state/delta merging,
 * and convergence reconciliation.
 */
class SyncProtocol
{
    private string $localNodeId;
    private VectorClock $clock;

    public function __construct(string $localNodeId, ?VectorClock $clock = null)
    {
        $this->localNodeId = $localNodeId;
        $this->clock       = $clock ?? new VectorClock([$localNodeId => 0]);
    }

    public function getLocalNodeId(): string
    {
        return $this->localNodeId;
    }

    public function getClock(): VectorClock
    {
        return $this->clock;
    }

    /**
     * Build an outbound synchronization message for a target document CRDT.
     */
    public function createSyncMessage(string $documentId, CrdtInterface $crdt, SyncMode $mode = SyncMode::STATE_BASED): SyncMessage
    {
        $this->clock->increment($this->localNodeId);

        $payload = match ($mode) {
            SyncMode::STATE_BASED     => $crdt->getState(),
            SyncMode::DELTA_BASED     => $crdt->delta($this->clock->toArray()),
            SyncMode::OPERATION_BASED => $crdt->getState(),
        };

        return new SyncMessage(
            senderId: $this->localNodeId,
            documentId: $documentId,
            mode: $mode,
            vectorClock: $this->clock->toArray(),
            payload: $payload
        );
    }

    /**
     * Process an inbound synchronization message and merge into target CRDT.
     *
     * @return array{converged_value: mixed, causality: string}
     */
    public function handleInboundMessage(SyncMessage $message, CrdtInterface $targetCrdt): array
    {
        $remoteClock = VectorClock::fromArray($message->getVectorClock());
        $causality = $this->clock->relationTo($remoteClock);

        // Update local vector clock
        $this->clock->merge($remoteClock);

        // Merge state or delta into CRDT
        $targetCrdt->merge($message->getPayload());

        return [
            'converged_value' => $targetCrdt->getValue(),
            'causality'       => $causality,
        ];
    }
}
