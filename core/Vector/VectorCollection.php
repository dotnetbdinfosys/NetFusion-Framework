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

namespace NetFusion\Core\Vector;

use NetFusion\Core\Vector\Contracts\EmbeddingInterface;
use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Contracts\DocumentSplitterInterface;
use NetFusion\Core\Vector\HNSW\HNSWGraph;
use NetFusion\Core\Vector\Search\BM25Index;
use NetFusion\Core\Vector\Search\ReciprocalRankFusion;
use NetFusion\Core\Vector\Search\ReRanker;
use NetFusion\Core\Vector\Chunking\RecursiveCharacterSplitter;
use NetFusion\Core\Vector\Math\DistanceMetric;
use NetFusion\Core\Vector\Math\Vector;
use JsonSerializable;

class VectorCollection implements JsonSerializable
{
    protected HNSWGraph $hnsw;
    protected BM25Index $bm25;

    /**
     * @var array<string, VectorDocument>
     */
    protected array $documents = [];

    /**
     * @param string $name Collection name
     * @param int $dimension Vector dimension
     * @param DistanceMetric $metric Distance metric
     * @param array<string, mixed> $metadata Collection metadata
     * @param HNSWGraph|null $hnsw
     * @param BM25Index|null $bm25
     * @param EmbeddingInterface|null $embeddingProvider
     * @param DocumentSplitterInterface|null $splitter
     */
    public function __construct(
        protected string $name,
        protected int $dimension = 64,
        protected DistanceMetric $metric = DistanceMetric::COSINE,
        protected array $metadata = [],
        ?HNSWGraph $hnsw = null,
        ?BM25Index $bm25 = null,
        protected ?EmbeddingInterface $embeddingProvider = null,
        protected ?DocumentSplitterInterface $splitter = null
    ) {
        $this->hnsw = $hnsw ?? new HNSWGraph(
            M: 16,
            efConstruction: 64,
            efSearch: 32,
            metric: $this->metric,
            dimension: $this->dimension
        );
        $this->bm25 = $bm25 ?? new BM25Index();
        $this->splitter = $this->splitter ?? new RecursiveCharacterSplitter(500, 100);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getMetric(): DistanceMetric
    {
        return $this->metric;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getHnsw(): HNSWGraph
    {
        return $this->hnsw;
    }

    public function getBm25(): BM25Index
    {
        return $this->bm25;
    }

    public function setEmbeddingProvider(EmbeddingInterface $provider): void
    {
        $this->embeddingProvider = $provider;
        $this->dimension = $provider->getDimension();
    }

    public function getEmbeddingProvider(): ?EmbeddingInterface
    {
        return $this->embeddingProvider;
    }

    public function setSplitter(DocumentSplitterInterface $splitter): void
    {
        $this->splitter = $splitter;
    }

    /**
     * Add a pre-embedded document to collection.
     */
    public function addDocument(VectorDocument $doc): void
    {
        $id = $doc->getId();
        $this->documents[$id] = $doc;

        // Add to BM25 sparse index
        $this->bm25->insert($id, $doc->getContent(), $doc->getMetadata());

        // Add to HNSW dense graph index if embedding is present
        $embedding = $doc->getEmbedding();
        if ($embedding !== null) {
            $this->hnsw->insert($id, $embedding, $doc->getMetadata());
        }
    }

    /**
     * Add raw text with automatic embedding and indexing.
     *
     * @param string $id Document ID
     * @param string $content Raw text
     * @param array<string, mixed> $metadata
     */
    public function addText(string $id, string $content, array $metadata = []): VectorDocument
    {
        $embedding = null;
        if ($this->embeddingProvider !== null) {
            $embedding = $this->embeddingProvider->embedText($content);
        }

        $doc = new VectorDocument($id, $content, $embedding, $metadata);
        $this->addDocument($doc);

        return $doc;
    }

    /**
     * Ingest a long document by automatically splitting it into chunks,
     * generating embeddings, and indexing all chunks.
     *
     * @param string $parentId Parent document ID
     * @param string $text Full text
     * @param array<string, mixed> $metadata
     * @return array<VectorDocument>
     */
    public function ingest(string $parentId, string $text, array $metadata = []): array
    {
        $splitter = $this->splitter ?? new RecursiveCharacterSplitter(500, 100);
        $chunks = $splitter->splitText($text, $metadata);

        if (empty($chunks)) {
            return [];
        }

        $chunkTexts = array_map(fn($c) => $c->getText(), $chunks);
        $embeddings = [];

        if ($this->embeddingProvider !== null) {
            $embeddings = $this->embeddingProvider->embedBatch($chunkTexts);
        }

        $docs = [];
        foreach ($chunks as $idx => $chunk) {
            $chunkId = $parentId . '#chunk_' . $chunk->getIndex();
            $emb = $embeddings[$idx] ?? null;

            $docMeta = array_merge($metadata, $chunk->getMetadata(), [
                'parent_id' => $parentId,
                'chunk_index' => $chunk->getIndex(),
                'token_count' => $chunk->getTokenCount(),
            ]);

            $doc = new VectorDocument(
                id: $chunkId,
                content: $chunk->getText(),
                embedding: $emb,
                metadata: $docMeta,
                parentId: $parentId,
                chunkIndex: $chunk->getIndex()
            );

            $this->addDocument($doc);
            $docs[] = $doc;
        }

        return $docs;
    }

    /**
     * Search collection using Hybrid, Dense, or Sparse search.
     *
     * @param string|VectorInterface $query Search query text or query vector
     * @param int $k Number of nearest results
     * @param array<string, mixed> $options {
     *     type: 'hybrid'|'dense'|'sparse',
     *     filter: array<string, mixed>,
     *     alpha: float (0.0 to 1.0 for score blending),
     *     rerank: bool (enable cross-reRanker),
     *     rrf_k: int (smoothing constant for RRF, default 60),
     * }
     * @return array<array{id: string, score: float, content: string, metadata: array<string, mixed>, document: ?VectorDocument}>
     */
    public function search(string|VectorInterface $query, int $k = 10, array $options = []): array
    {
        $type = (string)($options['type'] ?? 'hybrid');
        $filter = (array)($options['filter'] ?? []);
        $rerank = (bool)($options['rerank'] ?? true);

        $denseResults = [];
        $sparseResults = [];

        // 1. Dense Search
        if ($type === 'hybrid' || $type === 'dense') {
            $queryVector = null;
            if ($query instanceof VectorInterface) {
                $queryVector = $query;
            } elseif ($this->embeddingProvider !== null) {
                $queryVector = $this->embeddingProvider->embedText((string)$query);
            }

            if ($queryVector !== null) {
                $denseResults = $this->hnsw->search($queryVector, $k * 2, $filter);
            }
        }

        // 2. Sparse Search
        if ($type === 'hybrid' || $type === 'sparse') {
            $queryText = ($query instanceof VectorInterface) ? '' : (string)$query;
            if ($queryText !== '') {
                $sparseResults = $this->bm25->search($queryText, $k * 2, $filter);
            }
        }

        // 3. Merge / Select results
        $combined = [];
        if ($type === 'dense') {
            $combined = $denseResults;
        } elseif ($type === 'sparse') {
            $combined = $sparseResults;
        } else {
            // Hybrid Fusion
            $rrfK = (int)($options['rrf_k'] ?? 60);
            $combined = ReciprocalRankFusion::fuse(
                denseResults: $denseResults,
                sparseResults: $sparseResults,
                k: $rrfK,
                topK: $k * 2
            );
        }

        // 4. Optional Re-Ranking
        if ($rerank && !($query instanceof VectorInterface) && !empty($query)) {
            $combined = ReRanker::rerank((string)$query, $combined, $k);
        } else {
            $combined = array_slice($combined, 0, $k);
        }

        // 5. Enrich with full documents
        $formatted = [];
        foreach ($combined as $item) {
            $id = $item['id'];
            $doc = $this->documents[$id] ?? null;
            $content = $doc ? $doc->getContent() : (string)($item['metadata']['text'] ?? '');

            $formatted[] = [
                'id' => $id,
                'score' => (float)$item['score'],
                'content' => $content,
                'metadata' => $item['metadata'] ?? ($doc ? $doc->getMetadata() : []),
                'document' => $doc,
            ];
        }

        return $formatted;
    }

    public function getDocument(string $id): ?VectorDocument
    {
        return $this->documents[$id] ?? null;
    }

    public function hasDocument(string $id): bool
    {
        return isset($this->documents[$id]);
    }

    public function deleteDocument(string $id): bool
    {
        if (!isset($this->documents[$id])) {
            return false;
        }

        unset($this->documents[$id]);
        $this->hnsw->delete($id);
        $this->bm25->delete($id);

        return true;
    }

    /**
     * @return array<VectorDocument>
     */
    public function getDocuments(): array
    {
        return array_values($this->documents);
    }

    public function count(): int
    {
        return count($this->documents);
    }

    public function clear(): void
    {
        $this->documents = [];
        $this->hnsw->clear();
        $this->bm25->clear();
    }

    public function jsonSerialize(): array
    {
        $docs = [];
        foreach ($this->documents as $id => $doc) {
            $docs[$id] = $doc->jsonSerialize();
        }

        return [
            'name' => $this->name,
            'dimension' => $this->dimension,
            'metric' => $this->metric->value,
            'metadata' => $this->metadata,
            'hnsw' => $this->hnsw->jsonSerialize(),
            'bm25' => $this->bm25->jsonSerialize(),
            'documents' => $docs,
        ];
    }

    public static function fromArray(array $data, ?EmbeddingInterface $embeddingProvider = null): self
    {
        $metric = DistanceMetric::tryFrom((string)($data['metric'] ?? 'cosine')) ?? DistanceMetric::COSINE;
        $hnsw = isset($data['hnsw']) && is_array($data['hnsw']) ? HNSWGraph::fromArray($data['hnsw']) : null;
        $bm25 = isset($data['bm25']) && is_array($data['bm25']) ? BM25Index::fromArray($data['bm25']) : null;

        $col = new self(
            (string)($data['name'] ?? 'default'),
            (int)($data['dimension'] ?? 64),
            $metric,
            (array)($data['metadata'] ?? []),
            $hnsw,
            $bm25,
            $embeddingProvider
        );

        if (isset($data['documents']) && is_array($data['documents'])) {
            foreach ($data['documents'] as $id => $docData) {
                $doc = VectorDocument::fromArray((array)$docData);
                $col->documents[(string)$id] = $doc;
            }
        }

        return $col;
    }
}
