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

use NetFusion\Core\Vector\Contracts\VectorStoreInterface;
use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Contracts\EmbeddingInterface;
use NetFusion\Core\Vector\Embedding\EmbeddingManager;
use NetFusion\Core\Vector\Store\ArrayVectorStore;
use NetFusion\Core\Vector\Store\DatabaseVectorStore;
use NetFusion\Core\Vector\Math\DistanceMetric;
use NetFusion\Core\Vector\RAG\RAGPipeline;
use NetFusion\Core\Vector\Exceptions\CollectionNotFoundException;
use NetFusion\Core\Database\Connection;
use PDO;

class VectorEngineManager
{
    protected VectorStoreInterface $store;
    protected EmbeddingManager $embeddingManager;
    protected ?RAGPipeline $ragPipeline = null;

    /**
     * @var array<string, VectorCollection> Cached collections in memory
     */
    protected array $loadedCollections = [];

    /**
     * @param array<string, mixed> $config
     * @param VectorStoreInterface|null $store
     * @param EmbeddingManager|null $embeddingManager
     */
    public function __construct(
        protected array $config = [],
        ?VectorStoreInterface $store = null,
        ?EmbeddingManager $embeddingManager = null
    ) {
        $embedConfig = $config['embeddings'] ?? [];
        $this->embeddingManager = $embeddingManager ?? new EmbeddingManager($embedConfig);

        if ($store !== null) {
            $this->store = $store;
        } else {
            $storeType = $config['store'] ?? 'array';
            if ($storeType === 'database') {
                $connection = $config['connection'] ?? null;
                if ($connection instanceof PDO || $connection instanceof Connection) {
                    $this->store = new DatabaseVectorStore($connection, $this->embeddingManager->driver());
                } else {
                    $this->store = new ArrayVectorStore();
                }
            } else {
                $this->store = new ArrayVectorStore();
            }
        }
    }

    public function getStore(): VectorStoreInterface
    {
        return $this->store;
    }

    public function getEmbeddingManager(): EmbeddingManager
    {
        return $this->embeddingManager;
    }

    public function embed(string $text, ?string $driver = null): VectorInterface
    {
        return $this->embeddingManager->embed($text, $driver);
    }

    /**
     * Get or create a collection by name.
     */
    public function collection(
        string $name,
        ?int $dimension = null,
        ?DistanceMetric $metric = null,
        array $metadata = []
    ): VectorCollection {
        if (isset($this->loadedCollections[$name])) {
            return $this->loadedCollections[$name];
        }

        $existing = $this->store->getCollection($name);
        if ($existing !== null) {
            $existing->setEmbeddingProvider($this->embeddingManager->driver());
            return $this->loadedCollections[$name] = $existing;
        }

        // Create new collection
        $dim = $dimension ?? $this->embeddingManager->driver()->getDimension();
        $met = $metric ?? DistanceMetric::COSINE;

        $newCol = new VectorCollection(
            name: $name,
            dimension: $dim,
            metric: $met,
            metadata: $metadata,
            embeddingProvider: $this->embeddingManager->driver()
        );

        $this->store->saveCollection($newCol);
        return $this->loadedCollections[$name] = $newCol;
    }

    /**
     * Check if collection exists.
     */
    public function hasCollection(string $name): bool
    {
        return isset($this->loadedCollections[$name]) || $this->store->hasCollection($name);
    }

    /**
     * List all collections.
     *
     * @return array<string>
     */
    public function listCollections(): array
    {
        $storeCols = $this->store->listCollections();
        return array_unique(array_merge($storeCols, array_keys($this->loadedCollections)));
    }

    /**
     * Delete collection.
     */
    public function deleteCollection(string $name): bool
    {
        unset($this->loadedCollections[$name]);
        return $this->store->deleteCollection($name);
    }

    /**
     * Persist memory collection to store.
     */
    public function persist(string $name): void
    {
        if (isset($this->loadedCollections[$name])) {
            $this->store->saveCollection($this->loadedCollections[$name]);
        }
    }

    /**
     * Search across a collection.
     *
     * @param string $collectionName
     * @param string|VectorInterface $query
     * @param int $k
     * @param array<string, mixed> $options
     */
    public function search(string $collectionName, string|VectorInterface $query, int $k = 10, array $options = []): array
    {
        $col = $this->collection($collectionName);
        return $col->search($query, $k, $options);
    }

    /**
     * Get RAG Pipeline instance.
     */
    public function rag(?array $defaultOptions = null): RAGPipeline
    {
        if ($this->ragPipeline === null || $defaultOptions !== null) {
            $opts = $defaultOptions ?? ($this->config['rag'] ?? []);
            $this->ragPipeline = new RAGPipeline($this, $opts);
        }

        return $this->ragPipeline;
    }
}
