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
 * Multi-Value Register (MV-Register) CRDT.
 * Retains all concurrent conflicting values until causally superseded.
 */
class MvRegister implements CrdtInterface
{
    private string $nodeId;
    /** @var array<string, array{value: mixed, ts: array}> dotString => entry */
    private array $entries = [];
    private LamportTimestamp $clock;

    public function __construct(string $nodeId, array $entries = [], ?LamportTimestamp $clock = null)
    {
        $this->nodeId  = $nodeId;
        $this->entries = $entries;
        $this->clock   = $clock ?? new LamportTimestamp(0, $nodeId);
    }

    public function getType(): CrdtType
    {
        return CrdtType::MV_REGISTER;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function set(mixed $val): self
    {
        $this->clock->tick();
        $dot = $this->nodeId . ':' . $this->clock->getCounter();

        // Clear entries dominated by this node's write
        $this->entries = [
            $dot => [
                'value' => $val,
                'ts'    => $this->clock->toArray(),
            ]
        ];

        return $this;
    }

    /**
     * Return all concurrent values. If no conflict, returns single value array.
     * @return array<mixed>
     */
    public function getValue(): array
    {
        $values = [];
        foreach ($this->entries as $entry) {
            $values[] = $entry['value'];
        }
        return array_values(array_unique($values, SORT_REGULAR));
    }

    public function getState(): array
    {
        return [
            'type'    => $this->getType()->value,
            'node_id' => $this->nodeId,
            'entries' => $this->entries,
            'clock'   => $this->clock->toArray(),
        ];
    }

    public function merge(array $remoteState): self
    {
        $remoteEntries = $remoteState['entries'] ?? [];
        $remoteClock   = LamportTimestamp::fromArray($remoteState['clock'] ?? []);
        $this->clock->witness($remoteClock->getCounter());

        // Union entries
        foreach ($remoteEntries as $dot => $entry) {
            if (!isset($this->entries[$dot])) {
                $this->entries[$dot] = $entry;
            }
        }

        return $this;
    }

    public function delta(?array $sinceVectorClock = null): array
    {
        return $this->getState();
    }
}
