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
 * Replicated Growable Array (RGA) CRDT for collaborative rich-text editing.
 * Guarantees Strong Eventual Consistency (SEC) across distributed replicas.
 * Characters maintain unique (LamportTimestamp, nodeId, seq) coordinates and left-sibling references.
 */
class RgaText implements CrdtInterface
{
    private string $nodeId;
    private LamportTimestamp $clock;
    private int $seq = 0;
    /** @var array<int, array{id: string, char: string, left_id: ?string, ts: array, deleted: bool}> */
    private array $nodes = [];

    public function __construct(string $nodeId, ?LamportTimestamp $clock = null, array $nodes = [])
    {
        $this->nodeId = $nodeId;
        $this->clock  = $clock ?? new LamportTimestamp(0, $nodeId);
        $this->nodes  = $nodes;
    }

    public function getType(): CrdtType
    {
        return CrdtType::RGA_TEXT;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getClock(): LamportTimestamp
    {
        return $this->clock;
    }

    /**
     * Insert text at 0-indexed visible text position.
     */
    public function insert(int $pos, string $text): self
    {
        $chars = mb_str_split($text);
        $prevId = $this->getCharIdAtVisibleIndex($pos - 1);

        foreach ($chars as $ch) {
            $this->clock->tick();
            $this->seq++;
            $id = $this->clock->getCounter() . '@' . $this->nodeId . ':' . $this->seq;

            $node = [
                'id'      => $id,
                'char'    => $ch,
                'left_id' => $prevId,
                'ts'      => $this->clock->toArray(),
                'deleted' => false,
            ];

            $this->integrateInsert($node);
            $prevId = $id; // Chain subsequent characters after this one
        }

        return $this;
    }

    /**
     * Find node ID at visible index (-1 returns null, indicating root).
     */
    private function getCharIdAtVisibleIndex(int $visibleIndex): ?string
    {
        if ($visibleIndex < 0) {
            return null;
        }

        $visibleCount = 0;
        foreach ($this->nodes as $node) {
            if (!$node['deleted']) {
                if ($visibleCount === $visibleIndex) {
                    return $node['id'];
                }
                $visibleCount++;
            }
        }

        for ($i = count($this->nodes) - 1; $i >= 0; $i--) {
            if (!$this->nodes[$i]['deleted']) {
                return $this->nodes[$i]['id'];
            }
        }

        return !empty($this->nodes) ? $this->nodes[count($this->nodes) - 1]['id'] : null;
    }

    /**
     * Integrate a new node into the RGA list using deterministic ordering rules.
     */
    private function integrateInsert(array $newNode): bool
    {
        // Check for duplicates
        foreach ($this->nodes as $existing) {
            if ($existing['id'] === $newNode['id']) {
                return false;
            }
        }

        $prevId = $newNode['left_id'];
        $insertIdx = 0;

        if ($prevId !== null) {
            $foundPrev = false;
            for ($i = 0; $i < count($this->nodes); $i++) {
                if ($this->nodes[$i]['id'] === $prevId) {
                    $insertIdx = $i + 1;
                    $foundPrev = true;
                    break;
                }
            }
            if (!$foundPrev) {
                $insertIdx = count($this->nodes);
            }
        }

        $newTs = LamportTimestamp::fromArray($newNode['ts'] ?? []);

        // Advance past any nodes with higher Lamport timestamp sharing the same slot
        while ($insertIdx < count($this->nodes)) {
            $current = $this->nodes[$insertIdx];

            if ($current['left_id'] !== null && $prevId !== null && $current['left_id'] !== $prevId) {
                $curTs = LamportTimestamp::fromArray($current['ts'] ?? []);
                if ($curTs->compareTo($newTs) < 0) {
                    break;
                }
            }

            $curTs = LamportTimestamp::fromArray($current['ts'] ?? []);
            if ($newTs->compareTo($curTs) > 0) {
                break;
            }

            $insertIdx++;
        }

        array_splice($this->nodes, $insertIdx, 0, [$newNode]);
        return true;
    }

    /**
     * Delete character(s) at 0-indexed visible text position (tombstone).
     */
    public function delete(int $pos, int $length = 1): self
    {
        for ($k = 0; $k < $length; $k++) {
            $visibleIdx = 0;
            foreach ($this->nodes as &$node) {
                if (!$node['deleted']) {
                    if ($visibleIdx === $pos) {
                        $node['deleted'] = true;
                        break;
                    }
                    $visibleIdx++;
                }
            }
            unset($node);
        }

        return $this;
    }

    /**
     * Get visible plain text.
     */
    public function getValue(): string
    {
        $str = '';
        foreach ($this->nodes as $node) {
            if (!$node['deleted']) {
                $str .= $node['char'];
            }
        }
        return $str;
    }

    public function length(): int
    {
        return mb_strlen($this->getValue());
    }

    public function getState(): array
    {
        return [
            'type'    => $this->getType()->value,
            'node_id' => $this->nodeId,
            'clock'   => $this->clock->toArray(),
            'seq'     => $this->seq,
            'nodes'   => $this->nodes,
        ];
    }

    /**
     * Commutative RGA merge across remote replicas.
     */
    public function merge(array $remoteState): self
    {
        $remoteNodes = $remoteState['nodes'] ?? [];
        $remoteClock = LamportTimestamp::fromArray($remoteState['clock'] ?? []);
        $this->clock->witness($remoteClock->getCounter());

        $existingById = [];
        foreach ($this->nodes as $i => $n) {
            $existingById[$n['id']] = $i;
        }

        foreach ($remoteNodes as $rNode) {
            $id = $rNode['id'];
            if (isset($existingById[$id])) {
                if ($rNode['deleted']) {
                    $this->nodes[$existingById[$id]]['deleted'] = true;
                }
            } else {
                $this->integrateInsert($rNode);
                // Re-index map
                $existingById = [];
                foreach ($this->nodes as $idx => $nodeItem) {
                    $existingById[$nodeItem['id']] = $idx;
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
