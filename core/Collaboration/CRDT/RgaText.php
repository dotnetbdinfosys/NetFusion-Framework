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

namespace NetFusion\Core\Collaboration\CRDT;

/**
 * Class RgaText
 *
 * Replicated Growable Array (RGA) CRDT sequence for collaborative plain-text editing.
 * Guarantees Strong Eventual Consistency (SEC) across distributed peers with zero coordinator.
 */
class RgaText
{
    /** @var RgaNode[] */
    private array $nodes = [];

    private int $clock = 0;
    private int $seq = 0;

    public function __construct(
        public readonly string $peerId = 'peer-1'
    ) {
    }

    public function getPeerId(): string
    {
        return $this->peerId;
    }

    public function getClock(): int
    {
        return $this->clock;
    }

    /**
     * Insert text at a given zero-based visible index.
     *
     * @param int $visibleIndex
     * @param string $text
     * @return RgaNode[] Newly created nodes
     */
    public function insert(int $visibleIndex, string $text): array
    {
        $created = [];
        $chars = mb_str_split($text);

        // Find the node before which or after which we are inserting
        $prevId = $this->getCharIdAtVisibleIndex($visibleIndex - 1);

        foreach ($chars as $char) {
            $this->clock++;
            $this->seq++;
            $id = new CharId($this->clock, $this->peerId, $this->seq);

            $node = new RgaNode($id, $prevId, $char, false, microtime(true));
            $this->integrateInsert($node);

            $created[] = $node;
            $prevId = $id; // Subsequent chars chain after this one
        }

        return $created;
    }

    /**
     * Delete character(s) starting from visible index.
     *
     * @param int $visibleIndex
     * @param int $length
     * @return CharId[] Deleted node IDs
     */
    public function delete(int $visibleIndex, int $length = 1): array
    {
        $deletedIds = [];
        $visibleCount = 0;

        foreach ($this->nodes as $node) {
            if ($node->deleted) {
                continue;
            }

            if ($visibleCount >= $visibleIndex && $visibleCount < ($visibleIndex + $length)) {
                $node->markDeleted();
                $deletedIds[] = $node->id;
            }

            $visibleCount++;
            if ($visibleCount >= $visibleIndex + $length) {
                break;
            }
        }

        return $deletedIds;
    }

    /**
     * Apply remote insert node.
     */
    public function applyInsert(RgaNode $node): bool
    {
        // Update local Lamport clock
        if ($node->id->clock > $this->clock) {
            $this->clock = $node->id->clock;
        }

        return $this->integrateInsert($node);
    }

    /**
     * Apply remote delete operation.
     */
    public function applyDelete(CharId $targetId): bool
    {
        if ($targetId->clock > $this->clock) {
            $this->clock = $targetId->clock;
        }

        foreach ($this->nodes as $node) {
            if ($node->id->compareTo($targetId) === 0) {
                if (!$node->deleted) {
                    $node->markDeleted();
                    return true;
                }
                return false;
            }
        }
        return false;
    }

    /**
     * Internal insertion integration using RGA ordering rules.
     */
    private function integrateInsert(RgaNode $newNode): bool
    {
        // Check for duplicates
        foreach ($this->nodes as $existing) {
            if ($existing->id->compareTo($newNode->id) === 0) {
                return false; // Already present
            }
        }

        $prevId = $newNode->prevId;
        $insertIdx = 0;

        if ($prevId !== null) {
            // Find the position of prevId
            $foundPrev = false;
            for ($i = 0; $i < count($this->nodes); $i++) {
                if ($this->nodes[$i]->id->compareTo($prevId) === 0) {
                    $insertIdx = $i + 1;
                    $foundPrev = true;
                    break;
                }
            }
            if (!$foundPrev) {
                // If prev node not found yet, append at end as fallback
                $insertIdx = count($this->nodes);
            }
        }

        // Advance past any nodes with higher CharId that share this same insertion slot
        while ($insertIdx < count($this->nodes)) {
            $current = $this->nodes[$insertIdx];

            // If current node's ancestor has already passed, stop
            if ($current->prevId !== null && $prevId !== null && $current->prevId->compareTo($prevId) !== 0) {
                // Compare ancestor clocks
                if ($current->id->compareTo($newNode->id) < 0) {
                    break;
                }
            }

            if ($newNode->id->compareTo($current->id) > 0) {
                // New node has higher priority; insert before current
                break;
            }

            $insertIdx++;
        }

        array_splice($this->nodes, $insertIdx, 0, [$newNode]);
        return true;
    }

    /**
     * Find CharId at visible character index. Returns null for index < 0 (root).
     */
    public function getCharIdAtVisibleIndex(int $visibleIndex): ?CharId
    {
        if ($visibleIndex < 0) {
            return null;
        }

        $visibleCount = 0;
        foreach ($this->nodes as $node) {
            if (!$node->deleted) {
                if ($visibleCount === $visibleIndex) {
                    return $node->id;
                }
                $visibleCount++;
            }
        }

        // If beyond end of visible text, return last non-deleted node or last node
        for ($i = count($this->nodes) - 1; $i >= 0; $i--) {
            if (!$this->nodes[$i]->deleted) {
                return $this->nodes[$i]->id;
            }
        }

        return !empty($this->nodes) ? $this->nodes[count($this->nodes) - 1]->id : null;
    }

    /**
     * Get full materialized visible text string.
     */
    public function getText(): string
    {
        $out = '';
        foreach ($this->nodes as $node) {
            if (!$node->deleted) {
                $out .= $node->value;
            }
        }
        return $out;
    }

    /**
     * Total visible character count.
     */
    public function length(): int
    {
        $count = 0;
        foreach ($this->nodes as $node) {
            if (!$node->deleted) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Merge another RgaText CRDT state into this one.
     */
    public function merge(RgaText $other): void
    {
        if ($other->clock > $this->clock) {
            $this->clock = $other->clock;
        }

        foreach ($other->nodes as $otherNode) {
            $existing = null;
            foreach ($this->nodes as $n) {
                if ($n->id->compareTo($otherNode->id) === 0) {
                    $existing = $n;
                    break;
                }
            }

            if ($existing === null) {
                $copy = new RgaNode(
                    $otherNode->id,
                    $otherNode->prevId,
                    $otherNode->value,
                    $otherNode->deleted,
                    $otherNode->timestamp
                );
                $this->integrateInsert($copy);
            } else {
                if ($otherNode->deleted && !$existing->deleted) {
                    $existing->markDeleted();
                }
            }
        }
    }

    /**
     * @return RgaNode[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function toArray(): array
    {
        return [
            'peerId' => $this->peerId,
            'clock' => $this->clock,
            'seq' => $this->seq,
            'nodes' => array_map(fn(RgaNode $n) => $n->toArray(), $this->nodes),
        ];
    }

    public static function fromArray(array $data): self
    {
        $instance = new self((string) ($data['peerId'] ?? 'peer-1'));
        $instance->clock = (int) ($data['clock'] ?? 0);
        $instance->seq = (int) ($data['seq'] ?? 0);

        if (isset($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as $nodeData) {
                $instance->nodes[] = RgaNode::fromArray($nodeData);
            }
        }

        return $instance;
    }
}
