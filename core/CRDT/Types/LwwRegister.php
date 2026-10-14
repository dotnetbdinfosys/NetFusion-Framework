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

namespace NetFusion\Core\CRDT\Types;

use NetFusion\Core\CRDT\Enums\CrdtType;
use NetFusion\Core\CRDT\Causality\LamportTimestamp;

/**
 * Last-Write-Wins (LWW) Register CRDT.
 * Concurrent mutations are resolved via Lamport timestamp with node-ID tie breaking.
 */
class LwwRegister implements CrdtInterface
{
    private string $nodeId;
    private mixed $value;
    private LamportTimestamp $timestamp;

    public function __construct(string $nodeId, mixed $initialValue = null, ?LamportTimestamp $timestamp = null)
    {
        $this->nodeId    = $nodeId;
        $this->value     = $initialValue;
        $this->timestamp = $timestamp ?? new LamportTimestamp(0, $nodeId);
    }

    public function getType(): CrdtType
    {
        return CrdtType::LWW_REGISTER;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getTimestamp(): LamportTimestamp
    {
        return $this->timestamp;
    }

    public function set(mixed $newValue): self
    {
        $this->timestamp->tick();
        $this->value = $newValue;
        return $this;
    }

    public function getState(): array
    {
        return [
            'type'      => $this->getType()->value,
            'node_id'   => $this->nodeId,
            'value'     => $this->value,
            'timestamp' => $this->timestamp->toArray(),
        ];
    }

    public function merge(array $remoteState): self
    {
        $remoteTs = LamportTimestamp::fromArray($remoteState['timestamp'] ?? []);
        
        // Remote wins if its timestamp is strictly greater
        if ($remoteTs->compareTo($this->timestamp) > 0) {
            $this->value = $remoteState['value'] ?? null;
            $this->timestamp = $remoteTs;
        }

        return $this;
    }

    public function delta(?array $sinceVectorClock = null): array
    {
        return $this->getState();
    }
}
