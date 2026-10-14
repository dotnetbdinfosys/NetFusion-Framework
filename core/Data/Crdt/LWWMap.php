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

namespace NetFusion\Core\Data\Crdt;

use InvalidArgumentException;
use NetFusion\Core\Data\Crdt\Contracts\CrdtInterface;

class LWWMap implements CrdtInterface
{
    /** @var array<string, array{value: mixed, timestamp: float, node_id: string, deleted: bool}> */
    protected array $entries = [];

    public function __construct(
        protected string $localNodeId = 'local'
    ) {
    }

    public function set(string $key, mixed $value, ?float $timestamp = null, ?string $nodeId = null): self
    {
        $ts = $timestamp ?? microtime(true);
        $node = $nodeId ?? $this->localNodeId;

        $this->entries[$key] = [
            'value' => $value,
            'timestamp' => $ts,
            'node_id' => $node,
            'deleted' => false,
        ];

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->entries[$key]) || $this->entries[$key]['deleted']) {
            return $default;
        }
        return $this->entries[$key]['value'];
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]) && !$this->entries[$key]['deleted'];
    }

    public function remove(string $key, ?float $timestamp = null, ?string $nodeId = null): self
    {
        $ts = $timestamp ?? microtime(true);
        $node = $nodeId ?? $this->localNodeId;

        if (!isset($this->entries[$key])) {
            $this->entries[$key] = [
                'value' => null,
                'timestamp' => $ts,
                'node_id' => $node,
                'deleted' => true,
            ];
        } else {
            $this->entries[$key]['deleted'] = true;
            $this->entries[$key]['timestamp'] = $ts;
            $this->entries[$key]['node_id'] = $node;
        }

        return $this;
    }

    public function getValue(): array
    {
        $active = [];
        foreach ($this->entries as $k => $item) {
            if (!$item['deleted']) {
                $active[$k] = $item['value'];
            }
        }
        return $active;
    }

    public function merge(CrdtInterface $other): static
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Cannot merge LWWMap with non-LWWMap CRDT.');
        }

        $otherState = $other->getState();
        foreach ($otherState['entries'] as $key => $otherItem) {
            if (!isset($this->entries[$key])) {
                $this->entries[$key] = $otherItem;
                continue;
            }

            $local = $this->entries[$key];
            $otherTs = (float)$otherItem['timestamp'];
            $localTs = (float)$local['timestamp'];

            if ($otherTs > $localTs) {
                $this->entries[$key] = $otherItem;
            } elseif ($otherTs === $localTs) {
                // Tie breaker on node_id
                if (strcmp((string)$otherItem['node_id'], (string)$local['node_id']) > 0) {
                    $this->entries[$key] = $otherItem;
                }
            }
        }

        return $this;
    }

    public function getState(): array
    {
        return [
            'type' => 'lww-map',
            'entries' => $this->entries,
            'local_node' => $this->localNodeId,
        ];
    }

    public static function fromState(array $state): static
    {
        $instance = new static((string)($state['local_node'] ?? 'local'));
        $instance->entries = (array)($state['entries'] ?? []);
        return $instance;
    }

    public function jsonSerialize(): array
    {
        return [
            'entries' => $this->getValue(),
            'state' => $this->getState(),
        ];
    }
}
