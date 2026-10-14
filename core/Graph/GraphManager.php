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

namespace NetFusion\Core\Graph;

use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Storage\MemoryGraphStore;
use NetFusion\Core\Graph\Storage\DatabaseGraphStore;
use NetFusion\Core\Graph\Cypher\CypherEngine;
use NetFusion\Core\Graph\Algorithms\GraphTraverser;
use NetFusion\Core\Graph\Algorithms\PageRank;
use NetFusion\Core\Graph\Algorithms\CommunityDetection;
use NetFusion\Core\Graph\Algorithms\ConnectedComponents;
use NetFusion\Core\Graph\Reasoner\InferenceEngine;
use NetFusion\Core\Graph\Models\QueryResult;
use NetFusion\Core\Graph\Models\Path;
use PDO;

class GraphManager
{
    /**
     * @var array<string, GraphStoreInterface>
     */
    protected array $stores = [];

    /**
     * @var array<string, InferenceEngine>
     */
    protected array $reasoners = [];

    public function __construct(
        protected array $config = [],
        protected ?PDO $pdo = null
    ) {
    }

    public function graph(?string $name = null): GraphStoreInterface
    {
        $name = $name ?? ($this->config['default'] ?? 'default');

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }

        return $this->stores[$name];
    }

    public function setGraph(string $name, GraphStoreInterface $store): static
    {
        $this->stores[$name] = $store;
        return $this;
    }

    public function createStore(string $name): GraphStoreInterface
    {
        $driver = $this->config['driver'] ?? 'memory';
        if ($driver === 'database' && $this->pdo !== null) {
            return new DatabaseGraphStore($this->pdo);
        }
        return new MemoryGraphStore();
    }

    /**
     * Execute a Cypher query against a named graph.
     */
    public function query(string $cypher, array $parameters = [], ?string $graphName = null): QueryResult
    {
        $store = $this->graph($graphName);
        $engine = new CypherEngine($store);
        return $engine->query($cypher, $parameters);
    }

    /**
     * Graph Traverser helper.
     */
    public function traverser(?string $graphName = null): GraphTraverser
    {
        return new GraphTraverser($this->graph($graphName));
    }

    /**
     * Shortest path helper.
     */
    public function shortestPath(string $startId, string $targetId, ?string $relType = null, ?string $graphName = null): ?Path
    {
        return $this->traverser($graphName)->shortestPath($startId, $targetId, $relType);
    }

    /**
     * PageRank helper.
     */
    public function pageRank(?string $graphName = null, float $damping = 0.85, int $maxIter = 100): array
    {
        $pr = new PageRank($this->graph($graphName), $damping, $maxIter);
        return $pr->compute();
    }

    /**
     * Community Detection helper.
     */
    public function communities(?string $graphName = null, int $maxIter = 50): array
    {
        $cd = new CommunityDetection($this->graph($graphName), $maxIter);
        return $cd->getClusters();
    }

    /**
     * Connected Components helper.
     */
    public function connectedComponents(?string $graphName = null): array
    {
        $cc = new ConnectedComponents($this->graph($graphName));
        return $cc->findWeaklyConnected();
    }

    /**
     * Semantic Inference Reasoner helper.
     */
    public function reasoner(string $name = 'default'): InferenceEngine
    {
        if (!isset($this->reasoners[$name])) {
            $this->reasoners[$name] = new InferenceEngine();
        }
        return $this->reasoners[$name];
    }

    /**
     * Export graph to DOT format (Graphviz).
     */
    public function toDot(?string $graphName = null): string
    {
        $store = $this->graph($graphName);
        $lines = ["digraph G {"];

        foreach ($store->getNodes() as $node) {
            $lbl = implode(':', $node->getLabels());
            $name = $node->getProperty('name') ?? $node->getId();
            $title = $lbl !== '' ? "{$name} ({$lbl})" : $name;
            $lines[] = sprintf('  "%s" [label="%s"];', addslashes($node->getId()), addslashes((string)$title));
        }

        foreach ($store->getEdges() as $edge) {
            $arrow = $edge->isDirected() ? '->' : '--';
            $type = $edge->getType();
            $lines[] = sprintf('  "%s" %s "%s" [label="%s", weight=%.2f];', addslashes($edge->getSourceId()), $arrow, addslashes($edge->getTargetId()), addslashes($type), $edge->getWeight());
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }

    /**
     * Export graph to JSON format.
     */
    public function toJson(?string $graphName = null): string
    {
        $store = $this->graph($graphName);
        $nodes = array_map(fn(NodeInterface $n) => $n->toArray(), array_values($store->getNodes()));
        $edges = array_map(fn(EdgeInterface $e) => $e->toArray(), array_values($store->getEdges()));

        return json_encode([
            'nodes' => $nodes,
            'edges' => $edges,
            'node_count' => count($nodes),
            'edge_count' => count($edges),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
