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

class HNSWGraph implements VectorIndexInterface
{
    /**
     * @var array<string, HNSWNode> Node storage indexed by node ID
     */
    protected array $nodes = [];

    /**
     * Entry point node ID in the graph.
     */
    protected ?string $entryPointId = null;

    /**
     * Maximum level present in the graph.
     */
    protected int $maxLevel = -1;

    /**
     * Level generation normalization factor (1 / ln(M)).
     */
    protected float $mL;

    /**
     * @param int $M Maximum number of outgoing links per node for level > 0
     * @param int $efConstruction Size of dynamic candidate list during graph construction
     * @param int $efSearch Size of dynamic candidate list during query search
     * @param DistanceMetric $metric Distance metric to use
     * @param int|null $dimension Expected vector dimension (null for dynamic)
     */
    public function __construct(
        protected int $M = 16,
        protected int $efConstruction = 64,
        protected int $efSearch = 32,
        protected DistanceMetric $metric = DistanceMetric::COSINE,
        protected ?int $dimension = null
    ) {
        $this->mL = 1.0 / log(max(2, $this->M));
    }

    public function getMetric(): DistanceMetric
    {
        return $this->metric;
    }

    public function getM(): int
    {
        return $this->M;
    }

    public function getEfConstruction(): int
    {
        return $this->efConstruction;
    }

    public function getEfSearch(): int
    {
        return $this->efSearch;
    }

    public function setEfSearch(int $efSearch): void
    {
        $this->efSearch = max(1, $efSearch);
    }

    public function getDimension(): ?int
    {
        return $this->dimension;
    }

    /**
     * Generate random level for new node based on exponential decay distribution.
     */
    protected function randomLevel(): int
    {
        $uniform = (mt_rand(1, 1000000)) / 1000000.0;
        $lvl = (int)floor(-log($uniform) * $this->mL);
        return min($lvl, 16); // cap max layer height
    }

    /**
     * Calculate distance between vector and a node in the graph.
     */
    protected function distance(VectorInterface $query, HNSWNode $node): float
    {
        return $this->metric->calculateDistance($query, $node->getVector());
    }

    public function insert(string $id, VectorInterface $vector, array $metadata = []): void
    {
        if ($this->dimension === null) {
            $this->dimension = $vector->dimension();
        } elseif ($this->dimension !== $vector->dimension()) {
            throw new DimensionMismatchException($this->dimension, $vector->dimension());
        }

        // If node already exists, update vector/metadata and reconnect
        if (isset($this->nodes[$id])) {
            $this->delete($id);
        }

        $nodeLevel = $this->randomLevel();
        $newNode = new HNSWNode($id, $vector, $nodeLevel, $metadata);
        $this->nodes[$id] = $newNode;

        // First node in graph becomes the entry point
        if ($this->entryPointId === null || !isset($this->nodes[$this->entryPointId])) {
            $this->entryPointId = $id;
            $this->maxLevel = $nodeLevel;
            return;
        }

        $currObj = $this->nodes[$this->entryPointId];
        $currDist = $this->distance($vector, $currObj);

        // 1. Traverse top-down from maxLevel down to nodeLevel + 1 with greedy 1-NN search
        for ($level = $this->maxLevel; $level > $nodeLevel; $level--) {
            $changed = true;
            while ($changed) {
                $changed = false;
                foreach ($currObj->getNeighbors($level) as $neighborId) {
                    if (!isset($this->nodes[$neighborId])) {
                        continue;
                    }
                    $neighbor = $this->nodes[$neighborId];
                    $d = $this->distance($vector, $neighbor);
                    if ($d < $currDist) {
                        $currDist = $d;
                        $currObj = $neighbor;
                        $changed = true;
                    }
                }
            }
        }

        // 2. From min(maxLevel, nodeLevel) down to 0, search efConstruction nearest elements and connect
        $entryPointCandidates = [$currObj->getId() => $currDist];

        for ($level = min($this->maxLevel, $nodeLevel); $level >= 0; $level--) {
            $candidates = $this->searchLayer($vector, $entryPointCandidates, $this->efConstruction, $level);
            
            // Select M neighbors for level > 0, or 2*M for level 0
            $maxLinks = ($level === 0) ? ($this->M * 2) : $this->M;
            $selectedNeighbors = $this->selectNeighborsHeuristic($vector, $candidates, $maxLinks);

            // Connect new node to selected neighbors
            foreach ($selectedNeighbors as $nbrId) {
                $newNode->addNeighbor($level, $nbrId);
                $nbrNode = $this->nodes[$nbrId];
                $nbrNode->addNeighbor($level, $id);

                // Prune neighbor's links if exceeding limit
                $nbrLinks = $nbrNode->getNeighbors($level);
                if (count($nbrLinks) > $maxLinks) {
                    $pruned = $this->selectNeighborsHeuristic(
                        $nbrNode->getVector(),
                        $this->mapNodeDistances($nbrNode->getVector(), $nbrLinks),
                        $maxLinks
                    );
                    $nbrNode->setNeighbors($level, $pruned);
                }
            }

            $entryPointCandidates = $candidates;
        }

        // Update entry point if new node has higher level
        if ($nodeLevel > $this->maxLevel) {
            $this->maxLevel = $nodeLevel;
            $this->entryPointId = $id;
        }
    }

    /**
     * Search nearest neighbors within a single layer using priority queue traversal.
     *
     * @param array<string, float> $entryPoints Map of [nodeId => distance]
     * @return array<string, float> Map of [nodeId => distance] sorted ascending
     */
    protected function searchLayer(VectorInterface $query, array $entryPoints, int $ef, int $level): array
    {
        $visited = [];
        $candidates = []; // min-heap / map sorted by distance asc
        $nearest = [];    // result set of size at most ef

        foreach ($entryPoints as $epId => $dist) {
            if (isset($this->nodes[$epId])) {
                $visited[$epId] = true;
                $candidates[$epId] = $dist;
                $nearest[$epId] = $dist;
            }
        }

        asort($candidates);
        asort($nearest);

        while (!empty($candidates)) {
            // Extract closest candidate
            reset($candidates);
            $currId = key($candidates);
            $currDist = current($candidates);
            unset($candidates[$currId]);

            // Get furthest element in current nearest result set
            end($nearest);
            $furthestDist = current($nearest);

            if ($currDist > $furthestDist && count($nearest) >= $ef) {
                break;
            }

            $currNode = $this->nodes[$currId];
            foreach ($currNode->getNeighbors($level) as $neighborId) {
                if (isset($visited[$neighborId]) || !isset($this->nodes[$neighborId])) {
                    continue;
                }

                $visited[$neighborId] = true;
                $neighbor = $this->nodes[$neighborId];
                $dist = $this->distance($query, $neighbor);

                end($nearest);
                $furthestDist = current($nearest);

                if ($dist < $furthestDist || count($nearest) < $ef) {
                    $candidates[$neighborId] = $dist;
                    $nearest[$neighborId] = $dist;
                    asort($candidates);
                    asort($nearest);

                    if (count($nearest) > $ef) {
                        // Drop furthest
                        array_pop($nearest);
                    }
                }
            }
        }

        return $nearest;
    }

    /**
     * Map node IDs to distances from a vector.
     *
     * @param array<string> $nodeIds
     * @return array<string, float>
     */
    protected function mapNodeDistances(VectorInterface $origin, array $nodeIds): array
    {
        $res = [];
        foreach ($nodeIds as $id) {
            if (isset($this->nodes[$id])) {
                $res[$id] = $this->distance($origin, $this->nodes[$id]);
            }
        }
        asort($res);
        return $res;
    }

    /**
     * Heuristic neighbor selection to ensure connectivity and diversity.
     *
     * @param array<string, float> $candidates Map of [nodeId => distance]
     * @return array<string> Selected neighbor IDs
     */
    protected function selectNeighborsHeuristic(VectorInterface $query, array $candidates, int $maxLinks): array
    {
        asort($candidates);
        $result = [];

        foreach ($candidates as $id => $dist) {
            if (count($result) >= $maxLinks) {
                break;
            }

            if (!isset($this->nodes[$id])) {
                continue;
            }

            $candidateNode = $this->nodes[$id];
            $isCloserToAnySelected = false;

            // Simple diversity check: is this candidate closer to any already selected neighbor than to query?
            foreach ($result as $selectedId) {
                $selectedNode = $this->nodes[$selectedId];
                $interDist = $this->distance($candidateNode->getVector(), $selectedNode);
                if ($interDist < $dist) {
                    // It is closer to an existing neighbor, keep searching for diverse directions
                    // (Unless we have too few neighbors)
                    if (count($result) >= (int)($maxLinks / 2)) {
                        $isCloserToAnySelected = true;
                        break;
                    }
                }
            }

            if (!$isCloserToAnySelected) {
                $result[] = $id;
            }
        }

        // If not enough diverse neighbors selected, backfill from remaining candidates
        if (count($result) < $maxLinks) {
            foreach ($candidates as $id => $dist) {
                if (!in_array($id, $result, true)) {
                    $result[] = $id;
                    if (count($result) >= $maxLinks) {
                        break;
                    }
                }
            }
        }

        return $result;
    }

    public function search(VectorInterface $query, int $k = 10, array $filter = []): array
    {
        if (empty($this->nodes) || $this->entryPointId === null || !isset($this->nodes[$this->entryPointId])) {
            return [];
        }

        if ($this->dimension !== null && $this->dimension !== $query->dimension()) {
            throw new DimensionMismatchException($this->dimension, $query->dimension());
        }

        $currObj = $this->nodes[$this->entryPointId];
        $currDist = $this->distance($query, $currObj);

        // 1. Greedy search from top layer down to layer 1
        for ($level = $this->maxLevel; $level > 0; $level--) {
            $changed = true;
            while ($changed) {
                $changed = false;
                foreach ($currObj->getNeighbors($level) as $neighborId) {
                    if (!isset($this->nodes[$neighborId])) {
                        continue;
                    }
                    $neighbor = $this->nodes[$neighborId];
                    $d = $this->distance($query, $neighbor);
                    if ($d < $currDist) {
                        $currDist = $d;
                        $currObj = $neighbor;
                        $changed = true;
                    }
                }
            }
        }

        // 2. Search layer 0 with efSearch
        $ef = max($this->efSearch, $k);
        $candidates = $this->searchLayer($query, [$currObj->getId() => $currDist], $ef, 0);

        // 3. Filter metadata and format top-K results
        $results = [];
        foreach ($candidates as $id => $distance) {
            if (!isset($this->nodes[$id])) {
                continue;
            }

            $node = $this->nodes[$id];
            $metadata = $node->getMetadata();

            // Check metadata filtering
            if (!empty($filter) && !$this->matchesFilter($metadata, $filter)) {
                continue;
            }

            $score = $this->metric->calculateSimilarity($query, $node->getVector());
            $results[] = [
                'id' => $id,
                'score' => (float)$score,
                'distance' => (float)$distance,
                'metadata' => $metadata,
            ];

            if (count($results) >= $k) {
                break;
            }
        }

        return $results;
    }

    /**
     * Check if document metadata satisfies filter criteria.
     */
    protected function matchesFilter(array $metadata, array $filter): bool
    {
        foreach ($filter as $key => $expected) {
            $actual = $this->getNestedValue($metadata, (string)$key);

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

    /**
     * Helper to retrieve nested array values with dot notation.
     */
    protected function getNestedValue(array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $segments = explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function delete(string $id): bool
    {
        if (!isset($this->nodes[$id])) {
            return false;
        }

        $deletedNode = $this->nodes[$id];
        $level = $deletedNode->getLevel();

        // Unlink from all neighbors across all levels
        for ($l = 0; $l <= $level; $l++) {
            foreach ($deletedNode->getNeighbors($l) as $nbrId) {
                if (isset($this->nodes[$nbrId])) {
                    $this->nodes[$nbrId]->removeNeighbor($l, $id);
                }
            }
        }

        unset($this->nodes[$id]);

        // If deleted node was entry point, elect new entry point
        if ($this->entryPointId === $id) {
            $this->recalculateEntryPoint();
        }

        return true;
    }

    /**
     * Find node with maximum level as new entry point.
     */
    protected function recalculateEntryPoint(): void
    {
        $this->entryPointId = null;
        $this->maxLevel = -1;

        foreach ($this->nodes as $node) {
            if ($node->getLevel() > $this->maxLevel) {
                $this->maxLevel = $node->getLevel();
                $this->entryPointId = $node->getId();
            }
        }
    }

    public function has(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function getNode(string $id): ?HNSWNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    public function clear(): void
    {
        $this->nodes = [];
        $this->entryPointId = null;
        $this->maxLevel = -1;
    }

    public function jsonSerialize(): array
    {
        $serializedNodes = [];
        foreach ($this->nodes as $id => $node) {
            $serializedNodes[$id] = $node->jsonSerialize();
        }

        return [
            'M' => $this->M,
            'efConstruction' => $this->efConstruction,
            'efSearch' => $this->efSearch,
            'metric' => $this->metric->value,
            'dimension' => $this->dimension,
            'entryPointId' => $this->entryPointId,
            'maxLevel' => $this->maxLevel,
            'nodes' => $serializedNodes,
        ];
    }

    public static function fromArray(array $data): self
    {
        $metric = DistanceMetric::tryFrom((string)($data['metric'] ?? 'cosine')) ?? DistanceMetric::COSINE;
        $graph = new self(
            (int)($data['M'] ?? 16),
            (int)($data['efConstruction'] ?? 64),
            (int)($data['efSearch'] ?? 32),
            $metric,
            isset($data['dimension']) ? (int)$data['dimension'] : null
        );

        $graph->entryPointId = $data['entryPointId'] ?? null;
        $graph->maxLevel = (int)($data['maxLevel'] ?? -1);

        if (isset($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as $id => $nodeData) {
                $graph->nodes[(string)$id] = HNSWNode::fromArray((array)$nodeData);
            }
        }

        return $graph;
    }
}
