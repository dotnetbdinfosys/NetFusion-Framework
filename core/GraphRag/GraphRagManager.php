<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\GraphRag;

use NetFusion\Core\GraphRag\Chunking\DocumentChunker;
use NetFusion\Core\GraphRag\Entity\KnowledgeEdge;
use NetFusion\Core\GraphRag\Entity\KnowledgeNode;
use NetFusion\Core\GraphRag\Enums\FusionStrategy;
use NetFusion\Core\GraphRag\Enums\ModalityType;
use NetFusion\Core\GraphRag\Graph\VectorKnowledgeGraph;
use NetFusion\Core\GraphRag\Prompt\ContextSynthesizer;
use NetFusion\Core\GraphRag\Reranking\GraphReranker;
use NetFusion\Core\GraphRag\Retrieval\HybridRetriever;

/**
 * Master Enterprise Multi-Modal Vector Knowledge Graph & Hybrid Semantic RAG Manager.
 */
class GraphRagManager
{
    protected VectorKnowledgeGraph $graph;
    protected HybridRetriever $retriever;
    protected GraphReranker $reranker;

    /** @var array<string, mixed> */
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->graph = new VectorKnowledgeGraph();

        $rrfConstant = (int) ($config['rrf_constant'] ?? 60);
        $denseWeight = (float) ($config['dense_weight'] ?? 0.5);
        $sparseWeight = (float) ($config['sparse_weight'] ?? 0.3);
        $graphWeight = (float) ($config['graph_weight'] ?? 0.2);

        $this->retriever = new HybridRetriever(
            $this->graph,
            $rrfConstant,
            $denseWeight,
            $sparseWeight,
            $graphWeight
        );

        $centralityBonus = (float) ($config['centrality_bonus'] ?? 0.15);
        $this->reranker = new GraphReranker($this->graph, $centralityBonus);
    }

    public function getGraph(): VectorKnowledgeGraph
    {
        return $this->graph;
    }

    public function getRetriever(): HybridRetriever
    {
        return $this->retriever;
    }

    public function getReranker(): GraphReranker
    {
        return $this->reranker;
    }

    /**
     * Ingest a single knowledge entity or chunk into the vector graph.
     */
    public function ingest(
        string $id,
        string $content,
        array $vector = [],
        ModalityType $modality = ModalityType::TEXT,
        array $properties = []
    ): KnowledgeNode {
        $node = new KnowledgeNode($id, 'Entity', $content, $modality, $vector, $properties);
        $this->graph->addNode($node);
        return $node;
    }

    /**
     * Ingest and chunk an entire document, auto-linking chunks and extracted entity nodes.
     *
     * @param string $docId
     * @param string $text
     * @param array<int, array<int, float>> $chunkVectors Optional pre-computed vectors per chunk
     * @param int $chunkSize
     * @param int $overlap
     * @param array<string, mixed> $metadata
     * @return array<int, KnowledgeNode>
     */
    public function ingestDocument(
        string $docId,
        string $text,
        array $chunkVectors = [],
        int $chunkSize = 500,
        int $overlap = 100,
        array $metadata = []
    ): array {
        // Parent document node
        $parentDoc = new KnowledgeNode(
            $docId,
            'Document',
            mb_substr($text, 0, 200),
            ModalityType::DOCUMENT,
            [],
            $metadata
        );
        $this->graph->addNode($parentDoc);

        $chunks = DocumentChunker::chunkText($text, $chunkSize, $overlap);
        $createdNodes = [];

        foreach ($chunks as $idx => $chunkData) {
            $chunkId = "{$docId}_chk_{$idx}";
            $vec = $chunkVectors[$idx] ?? [];

            $chunkNode = new KnowledgeNode(
                $chunkId,
                'Chunk',
                $chunkData['text'],
                ModalityType::TEXT,
                $vec,
                array_merge($metadata, [
                    'docId'  => $docId,
                    'offset' => $chunkData['offset'],
                    'length' => $chunkData['length'],
                ])
            );
            $this->graph->addNode($chunkNode);
            $createdNodes[] = $chunkNode;

            // Link parent document to chunk: (Document)-[CONTAINS]->(Chunk)
            $this->link($docId, $chunkId, 'CONTAINS', 1.0);

            // Sequential chunk linkage: (Chunk[i-1])-[NEXT_CHUNK]->(Chunk[i])
            if ($idx > 0) {
                $prevChunkId = "{$docId}_chk_" . ($idx - 1);
                $this->link($prevChunkId, $chunkId, 'NEXT_CHUNK', 0.8);
            }

            // Extract entity anchors and link them
            $entities = DocumentChunker::extractEntityKeywords($chunkData['text']);
            foreach ($entities as $entityName) {
                $entityId = 'ent_' . mb_strtolower($entityName);
                if (!$this->graph->hasNode($entityId)) {
                    $entityNode = new KnowledgeNode($entityId, 'Concept', $entityName, ModalityType::TEXT);
                    $this->graph->addNode($entityNode);
                }
                $this->link($chunkId, $entityId, 'MENTIONS', 0.9);
            }
        }

        return $createdNodes;
    }

    /**
     * Link two nodes in the graph with a weighted relationship.
     */
    public function link(
        string $sourceId,
        string $targetId,
        string $relationship = 'RELATED_TO',
        float $weight = 1.0,
        array $properties = []
    ): KnowledgeEdge {
        $edge = new KnowledgeEdge($sourceId, $targetId, $relationship, $weight, $properties);
        $this->graph->addEdge($edge);
        return $edge;
    }

    /**
     * Execute Tri-Way Hybrid Retrieval and optional Graph Reranking.
     *
     * @param string $query
     * @param array<int, float> $queryVector
     * @param int $limit
     * @param bool $rerank
     * @param FusionStrategy $strategy
     * @return array<int, mixed>
     */
    public function retrieve(
        string $query,
        array $queryVector = [],
        int $limit = 5,
        bool $rerank = true,
        FusionStrategy $strategy = FusionStrategy::RECIPROCAL_RANK_FUSION
    ): array {
        $candidates = $this->retriever->retrieve(
            $query,
            $queryVector,
            $limit * 2,
            $strategy
        );

        if (!$rerank) {
            return array_slice($candidates, 0, $limit);
        }

        return $this->reranker->rerank($candidates, $limit);
    }

    /**
     * Retrieve hybrid context and synthesize into a grounded LLM prompt string.
     */
    public function synthesizeContext(string $query, array $queryVector = [], int $limit = 5): string
    {
        $retrieved = $this->retrieve($query, $queryVector, $limit, rerank: true);
        return ContextSynthesizer::synthesize($retrieved, $this->graph, $query);
    }

    /**
     * Export graph topology as serialized array.
     *
     * @return array{nodes: array<int, mixed>, edges: array<int, mixed>}
     */
    public function exportGraph(): array
    {
        $nodes = array_map(fn(KnowledgeNode $n) => $n->toArray(), array_values($this->graph->getNodes()));
        $edges = array_map(fn(KnowledgeEdge $e) => $e->toArray(), $this->graph->getEdges());

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
