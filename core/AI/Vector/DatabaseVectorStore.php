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

namespace NetFusion\Core\AI\Vector;

use NetFusion\Core\AI\Contracts\VectorStoreInterface;
use NetFusion\Core\Database\DatabaseManager;
use PDO;

class DatabaseVectorStore implements VectorStoreInterface
{
    protected string $table = 'ai_vector_documents';

    public function __construct(
        protected mixed $connection
    ) {
    }

    protected function getPdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if ($this->connection instanceof DatabaseManager) {
            return $this->connection->connection()->getPdo();
        }

        if (is_object($this->connection)) {
            if (method_exists($this->connection, 'getPdo')) {
                return $this->connection->getPdo();
            }
            if (method_exists($this->connection, 'connection')) {
                $conn = $this->connection->connection();
                if ($conn instanceof PDO) {
                    return $conn;
                }
                if (is_object($conn) && method_exists($conn, 'getPdo')) {
                    return $conn->getPdo();
                }
            }
        }

        throw new \RuntimeException("Unable to resolve PDO instance from connection.");
    }

    public function add(string $id, array $vector, string $content = '', array $metadata = [], string $collection = 'default'): void
    {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $jsonVector = json_encode(array_map('floatval', $vector));
        $jsonMetadata = json_encode($metadata);

        $stmt = $pdo->prepare("
            INSERT INTO {$this->table} (id, collection, vector, content, metadata, created_at, updated_at)
            VALUES (:id, :collection, :vector, :content, :metadata, :created_at, :updated_at)
            ON CONFLICT(id, collection) DO UPDATE SET
                vector = excluded.vector,
                content = excluded.content,
                metadata = excluded.metadata,
                updated_at = excluded.updated_at
        ");

        try {
            $stmt->execute([
                ':id' => $id,
                ':collection' => $collection,
                ':vector' => $jsonVector,
                ':content' => $content,
                ':metadata' => $jsonMetadata,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (\Throwable) {
            // MySQL fallback
            $stmtFallback = $pdo->prepare("
                INSERT INTO {$this->table} (id, collection, vector, content, metadata, created_at, updated_at)
                VALUES (:id, :collection, :vector, :content, :metadata, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    vector = VALUES(vector),
                    content = VALUES(content),
                    metadata = VALUES(metadata),
                    updated_at = VALUES(updated_at)
            ");
            $stmtFallback->execute([
                ':id' => $id,
                ':collection' => $collection,
                ':vector' => $jsonVector,
                ':content' => $content,
                ':metadata' => $jsonMetadata,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    public function search(array $queryVector, int $limit = 5, float $minScore = 0.0, string $collection = 'default'): array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("
            SELECT id, vector, content, metadata
            FROM {$this->table}
            WHERE collection = :collection
        ");
        $stmt->execute([':collection' => $collection]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $queryVector = array_map('floatval', $queryVector);
        $results = [];

        foreach ($rows as $row) {
            $docVector = json_decode((string)$row['vector'], true) ?? [];
            $score = VectorMath::cosineSimilarity($queryVector, $docVector);

            if ($score >= $minScore) {
                $meta = json_decode((string)$row['metadata'], true) ?? [];
                $results[] = [
                    'id' => (string)$row['id'],
                    'score' => round($score, 6),
                    'content' => (string)$row['content'],
                    'metadata' => is_array($meta) ? $meta : [],
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function delete(string $id, string $collection = 'default'): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id AND collection = :collection");
        $stmt->execute([
            ':id' => $id,
            ':collection' => $collection,
        ]);
    }

    public function count(string $collection = 'default'): int
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE collection = :collection");
        $stmt->execute([':collection' => $collection]);
        return (int)$stmt->fetchColumn();
    }

    public function clear(string $collection = 'default'): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE collection = :collection");
        $stmt->execute([':collection' => $collection]);
    }
}
