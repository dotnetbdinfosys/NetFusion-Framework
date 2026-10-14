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
 * Nested JSON/Document CRDT combining LWW key registers, sets and nested structures.
 */
class JsonCrdt implements CrdtInterface
{
    private string $nodeId;
    /** @var array<string, array{val: mixed, ts: array}> */
    private array $fields = [];
    private LamportTimestamp $clock;

    public function __construct(string $nodeId, array $fields = [], ?LamportTimestamp $clock = null)
    {
        $this->nodeId = $nodeId;
        $this->fields = $fields;
        $this->clock  = $clock ?? new LamportTimestamp(0, $nodeId);
    }

    public function getType(): CrdtType
    {
        return CrdtType::JSON_CRDT;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function set(string $key, mixed $value): self
    {
        $this->clock->tick();
        $this->fields[$key] = [
            'val'     => $value,
            'ts'      => $this->clock->toArray(),
            'deleted' => false,
        ];
        return $this;
    }

    public function delete(string $key): self
    {
        $this->clock->tick();
        $this->fields[$key] = [
            'val'     => null,
            'ts'      => $this->clock->toArray(),
            'deleted' => true,
        ];
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->fields[$key]) || ($this->fields[$key]['deleted'] ?? false)) {
            return $default;
        }
        return $this->fields[$key]['val'];
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]) && !($this->fields[$key]['deleted'] ?? false);
    }

    public function getValue(): array
    {
        $res = [];
        foreach ($this->fields as $k => $f) {
            if (!($f['deleted'] ?? false)) {
                $res[$k] = $f['val'];
            }
        }
        return $res;
    }

    public function getState(): array
    {
        return [
            'type'    => $this->getType()->value,
            'node_id' => $this->nodeId,
            'fields'  => $this->fields,
            'clock'   => $this->clock->toArray(),
        ];
    }

    public function merge(array $remoteState): self
    {
        $remoteFields = $remoteState['fields'] ?? [];
        $remoteClock  = LamportTimestamp::fromArray($remoteState['clock'] ?? []);
        $this->clock->witness($remoteClock->getCounter());

        foreach ($remoteFields as $key => $rField) {
            $rTs = LamportTimestamp::fromArray($rField['ts'] ?? []);
            
            if (!isset($this->fields[$key])) {
                $this->fields[$key] = $rField;
            } else {
                $localTs = LamportTimestamp::fromArray($this->fields[$key]['ts'] ?? []);
                if ($rTs->compareTo($localTs) > 0) {
                    $this->fields[$key] = $rField;
                }
            }
        }

        return $this;
    }

    public function delta(?array $sinceVectorClock = null): array
    {
        return $this->getState();
    }
}
