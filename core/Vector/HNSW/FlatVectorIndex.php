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

use NetFusion\Core\Vector\Contracts\VectorIndexInterface;
use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Math\DistanceMetric;
use NetFusion\Core\Vector\Math\Vector;
use NetFusion\Core\Vector\Exceptions\DimensionMismatchException;

class FlatVectorIndex implements VectorIndexInterface
{
    /**
     * @var array<string, array{vector: VectorInterface, metadata: array<string, mixed>}>
     */
    protected array $items = [];

    public function __construct(
        protected DistanceMetric $metric = DistanceMetric::COSINE,
        protected ?int $dimension = null
    ) {
    }

    public function insert(string $id, VectorInterface $vector, array $metadata = []): void
    {
        if ($this->dimension === null) {
            $this->dimension = $vector->dimension();
        } elseif ($this->dimension !== $vector->dimension()) {
            throw new DimensionMismatchException($this->dimension, $vector->dimension());
        }

        $this->items[$id] = [
            'vector' => $vector,
            'metadata' => $metadata,
        ];
    }

    public function search(VectorInterface $query, int $k = 10, array $filter = []): array
    {
        if (empty($this->items)) {
            return [];
        }

        if ($this->dimension !== null && $this->dimension !== $query->dimension()) {
            throw new DimensionMismatchException($this->dimension, $query->dimension());
        }

        $scores = [];
        foreach ($this->items as $id => $item) {
            $metadata = $item['metadata'];

            if (!empty($filter) && !$this->matchesFilter($metadata, $filter)) {
                continue;
            }

            $distance = $this->metric->calculateDistance($query, $item['vector']);
            $score = $this->metric->calculateSimilarity($query, $item['vector']);

            $scores[$id] = [
                'id' => $id,
                'score' => (float)$score,
                'distance' => (float)$distance,
                'metadata' => $metadata,
            ];
        }

        // Sort by distance ascending (nearest first)
        uasort($scores, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice(array_values($scores), 0, $k);
    }

    protected function matchesFilter(array $metadata, array $filter): bool
    {
        foreach ($filter as $key => $expected) {
            $actual = $metadata[$key] ?? null;
            if (is_array($expected)) {
                if (!in_array($actual, $expected, false)) {
                    return false;
                }
            } else {
                if ($actual != $expected) {
                    return false;
                }
            }
        }

        return true;
    }

    public function delete(string $id): bool
    {
        if (isset($this->items[$id])) {
            unset($this->items[$id]);
            return true;
        }

        return false;
    }

    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function jsonSerialize(): array
    {
        $serialized = [];
        foreach ($this->items as $id => $item) {
            $serialized[$id] = [
                'vector' => $item['vector']->toArray(),
                'metadata' => $item['metadata'],
            ];
        }

        return [
            'metric' => $this->metric->value,
            'dimension' => $this->dimension,
            'items' => $serialized,
        ];
    }

    public static function fromArray(array $data): self
    {
        $metric = DistanceMetric::tryFrom((string)($data['metric'] ?? 'cosine')) ?? DistanceMetric::COSINE;
        $index = new self($metric, isset($data['dimension']) ? (int)$data['dimension'] : null);

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $id => $itemData) {
                $vec = new Vector((array)($itemData['vector'] ?? []));
                $index->items[(string)$id] = [
                    'vector' => $vec,
                    'metadata' => (array)($itemData['metadata'] ?? []),
                ];
            }
        }

        return $index;
    }
}
