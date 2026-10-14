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

namespace NetFusion\Core\Vector\HNSW;

use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Math\Vector;
use JsonSerializable;

class HNSWNode implements JsonSerializable
{
    /**
     * @var array<int, array<string, bool>> Neighbor IDs indexed by level: [level => [neighborId => true]]
     */
    protected array $neighbors = [];

    /**
     * @param string $id Unique identifier
     * @param VectorInterface $vector Vector embedding
     * @param int $level Maximum level of this node (0..level)
     * @param array<string, mixed> $metadata Additional document metadata
     */
    public function __construct(
        protected string $id,
        protected VectorInterface $vector,
        protected int $level,
        protected array $metadata = []
    ) {
        for ($l = 0; $l <= $level; $l++) {
            $this->neighbors[$l] = [];
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVector(): VectorInterface
    {
        return $this->vector;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Get neighbors at a specific level.
     *
     * @return array<string>
     */
    public function getNeighbors(int $level): array
    {
        return array_keys($this->neighbors[$level] ?? []);
    }

    /**
     * Add neighbor link at a specific level.
     */
    public function addNeighbor(int $level, string $neighborId): void
    {
        if ($neighborId === $this->id) {
            return;
        }

        if (!isset($this->neighbors[$level])) {
            $this->neighbors[$level] = [];
        }

        $this->neighbors[$level][$neighborId] = true;
    }

    /**
     * Remove neighbor link at a specific level.
     */
    public function removeNeighbor(int $level, string $neighborId): void
    {
        unset($this->neighbors[$level][$neighborId]);
    }

    /**
     * Set exact neighbor list at a specific level.
     *
     * @param array<string> $neighborIds
     */
    public function setNeighbors(int $level, array $neighborIds): void
    {
        $this->neighbors[$level] = [];
        foreach ($neighborIds as $nid) {
            if ($nid !== $this->id) {
                $this->neighbors[$level][$nid] = true;
            }
        }
    }

    public function jsonSerialize(): array
    {
        $serializedNeighbors = [];
        foreach ($this->neighbors as $lvl => $nbrMap) {
            $serializedNeighbors[$lvl] = array_keys($nbrMap);
        }

        return [
            'id' => $this->id,
            'vector' => $this->vector->toArray(),
            'level' => $this->level,
            'metadata' => $this->metadata,
            'neighbors' => $serializedNeighbors,
        ];
    }

    public static function fromArray(array $data): self
    {
        $vec = new Vector($data['vector'] ?? []);
        $node = new self(
            (string)($data['id'] ?? ''),
            $vec,
            (int)($data['level'] ?? 0),
            (array)($data['metadata'] ?? [])
        );

        if (isset($data['neighbors']) && is_array($data['neighbors'])) {
            foreach ($data['neighbors'] as $lvl => $nbrs) {
                $node->setNeighbors((int)$lvl, (array)$nbrs);
            }
        }

        return $node;
    }
}
