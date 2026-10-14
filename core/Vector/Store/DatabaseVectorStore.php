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

namespace NetFusion\Core\Vector\Store;

use NetFusion\Core\Vector\Contracts\VectorStoreInterface;
use NetFusion\Core\Vector\Contracts\EmbeddingInterface;
use NetFusion\Core\Vector\VectorCollection;
use NetFusion\Core\Vector\VectorDocument;
use NetFusion\Core\Vector\Math\Vector;
use NetFusion\Core\Database\Connection;
use PDO;

class DatabaseVectorStore implements VectorStoreInterface
{
    protected PDO $pdo;

    /**
     * @param PDO|Connection $connection
     * @param EmbeddingInterface|null $embeddingProvider
     */
    public function __construct(
        PDO|Connection $connection,
        protected ?EmbeddingInterface $embeddingProvider = null
    ) {
        $this->pdo = ($connection instanceof Connection) ? $connection->getPdo() : $connection;
    }

    public function saveCollection(VectorCollection $collection): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO nf_vector_collections (name, dimension, metric, metadata, created_at, updated_at)
            VALUES (:name, :dim, :metric, :meta, :created_at, :updated_at)
            ON CONFLICT(name) DO UPDATE SET
                dimension = :dim_up,
                metric = :metric_up,
                metadata = :meta_up,
                updated_at = :updated_at_up
        ");

        $now = time();
        $metaJson = json_encode($collection->getMetadata(), JSON_THROW_ON_ERROR);

        $stmt->execute([
            ':name' => $collection->getName(),
            ':dim' => $collection->getDimension(),
            ':metric' => $collection->getMetric()->value,
            ':meta' => $metaJson,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':dim_up' => $collection->getDimension(),
            ':metric_up' => $collection->getMetric()->value,
            ':meta_up' => $metaJson,
            ':updated_at_up' => $now,
        ]);

        // Save Graph and Index State
        $this->saveGraphState($collection);

        // Save All Documents
        foreach ($collection->getDocuments() as $doc) {
            $this->saveDocument($collection->getName(), $doc);
        }
    }

    protected function saveGraphState(VectorCollection $collection): void
    {
        $graphJson = json_encode($collection->getHnsw()->jsonSerialize(), JSON_THROW_ON_ERROR);
        $bm25Json = json_encode($collection->getBm25()->jsonSerialize(), JSON_THROW_ON_ERROR);

        $stmt = $this->pdo->prepare("
            INSERT INTO nf_vector_graphs (collection_name, hnsw_data, bm25_data, updated_at)
            VALUES (:name, :hnsw, :bm25, :updated_at)
            ON CONFLICT(collection_name) DO UPDATE SET
                hnsw_data = :hnsw_up,
                bm25_data = :bm25_up,
                updated_at = :updated_at_up
        ");

        $now = time();
        $stmt->execute([
            ':name' => $collection->getName(),
            ':hnsw' => $graphJson,
            ':bm25' => $bm25Json,
            ':updated_at' => $now,
            ':hnsw_up' => $graphJson,
            ':bm25_up' => $bm25Json,
            ':updated_at_up' => $now,
        ]);
    }

    public function getCollection(string $name): ?VectorCollection
    {
        $stmt = $this->pdo->prepare("SELECT * FROM nf_vector_collections WHERE name = :name");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Fetch graph data if available
        $gStmt = $this->pdo->prepare("SELECT * FROM nf_vector_graphs WHERE collection_name = :name");
        $gStmt->execute([':name' => $name]);
        $gRow = $gStmt->fetch(PDO::FETCH_ASSOC);

        $hnswData = ($gRow && !empty($gRow['hnsw_data'])) ? json_decode((string)$gRow['hnsw_data'], true) : null;
        $bm25Data = ($gRow && !empty($gRow['bm25_data'])) ? json_decode((string)$gRow['bm25_data'], true) : null;

        $meta = !empty($row['metadata']) ? (array)json_decode((string)$row['metadata'], true) : [];

        $collection = VectorCollection::fromArray([
            'name' => $row['name'],
            'dimension' => (int)$row['dimension'],
            'metric' => $row['metric'] ?? 'cosine',
            'metadata' => $meta,
            'hnsw' => $hnswData,
            'bm25' => $bm25Data,
        ], $this->embeddingProvider);

        // Fetch Documents
        $dStmt = $this->pdo->prepare("SELECT * FROM nf_vector_documents WHERE collection_name = :name");
        $dStmt->execute([':name' => $name]);
        while ($dRow = $dStmt->fetch(PDO::FETCH_ASSOC)) {
            $embedding = null;
            if (!empty($dRow['embedding'])) {
                $embedding = new Vector((array)json_decode((string)$dRow['embedding'], true));
            }
            $docMeta = !empty($dRow['metadata']) ? (array)json_decode((string)$dRow['metadata'], true) : [];

            $doc = new VectorDocument(
                id: (string)$dRow['id'],
                content: (string)$dRow['content'],
                embedding: $embedding,
                metadata: $docMeta,
                parentId: $dRow['parent_id'] ?? null,
                chunkIndex: isset($dRow['chunk_index']) ? (int)$dRow['chunk_index'] : null,
                createdAt: (int)($dRow['created_at'] ?? time())
            );

            $collection->addDocument($doc);
        }

        return $collection;
    }

    public function hasCollection(string $name): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM nf_vector_collections WHERE name = :name");
        $stmt->execute([':name' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function deleteCollection(string $name): bool
    {
        $this->pdo->beginTransaction();
        try {
            $s1 = $this->pdo->prepare("DELETE FROM nf_vector_graphs WHERE collection_name = :name");
            $s1->execute([':name' => $name]);

            $s2 = $this->pdo->prepare("DELETE FROM nf_vector_documents WHERE collection_name = :name");
            $s2->execute([':name' => $name]);

            $s3 = $this->pdo->prepare("DELETE FROM nf_vector_collections WHERE name = :name");
            $s3->execute([':name' => $name]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function listCollections(): array
    {
        $stmt = $this->pdo->query("SELECT name FROM nf_vector_collections ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    public function saveDocument(string $collectionName, VectorDocument $document): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO nf_vector_documents (id, collection_name, parent_id, chunk_index, content, embedding, metadata, created_at)
            VALUES (:id, :cname, :pid, :cidx, :content, :emb, :meta, :created_at)
            ON CONFLICT(id, collection_name) DO UPDATE SET
                content = :content_up,
                embedding = :emb_up,
                metadata = :meta_up
        ");

        $embJson = $document->getEmbedding() ? json_encode($document->getEmbedding()->toArray(), JSON_THROW_ON_ERROR) : null;
        $metaJson = json_encode($document->getMetadata(), JSON_THROW_ON_ERROR);

        $stmt->execute([
            ':id' => $document->getId(),
            ':cname' => $collectionName,
            ':pid' => $document->getParentId(),
            ':cidx' => $document->getChunkIndex(),
            ':content' => $document->getContent(),
            ':emb' => $embJson,
            ':meta' => $metaJson,
            ':created_at' => $document->getCreatedAt(),
            ':content_up' => $document->getContent(),
            ':emb_up' => $embJson,
            ':meta_up' => $metaJson,
        ]);
    }

    public function deleteDocument(string $collectionName, string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM nf_vector_documents WHERE collection_name = :cname AND id = :id");
        $stmt->execute([':cname' => $collectionName, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function getDocument(string $collectionName, string $id): ?VectorDocument
    {
        $stmt = $this->pdo->prepare("SELECT * FROM nf_vector_documents WHERE collection_name = :cname AND id = :id");
        $stmt->execute([':cname' => $collectionName, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $embedding = !empty($row['embedding']) ? new Vector((array)json_decode((string)$row['embedding'], true)) : null;
        $meta = !empty($row['metadata']) ? (array)json_decode((string)$row['metadata'], true) : [];

        return new VectorDocument(
            id: (string)$row['id'],
            content: (string)$row['content'],
            embedding: $embedding,
            metadata: $meta,
            parentId: $row['parent_id'] ?? null,
            chunkIndex: isset($row['chunk_index']) ? (int)$row['chunk_index'] : null,
            createdAt: (int)($row['created_at'] ?? time())
        );
    }

    public function getDocuments(string $collectionName, int $limit = 1000, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM nf_vector_documents 
            WHERE collection_name = :cname 
            ORDER BY created_at ASC 
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':cname', $collectionName);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $docs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $embedding = !empty($row['embedding']) ? new Vector((array)json_decode((string)$row['embedding'], true)) : null;
            $meta = !empty($row['metadata']) ? (array)json_decode((string)$row['metadata'], true) : [];

            $docs[] = new VectorDocument(
                id: (string)$row['id'],
                content: (string)$row['content'],
                embedding: $embedding,
                metadata: $meta,
                parentId: $row['parent_id'] ?? null,
                chunkIndex: isset($row['chunk_index']) ? (int)$row['chunk_index'] : null,
                createdAt: (int)($row['created_at'] ?? time())
            );
        }

        return $docs;
    }

    public function countDocuments(string $collectionName): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM nf_vector_documents WHERE collection_name = :cname");
        $stmt->execute([':cname' => $collectionName]);
        return (int)$stmt->fetchColumn();
    }
}
