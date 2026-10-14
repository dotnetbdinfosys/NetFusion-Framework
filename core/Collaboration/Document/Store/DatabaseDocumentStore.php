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

namespace NetFusion\Core\Collaboration\Document\Store;

use PDO;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Collaboration\Document\Contracts\DocumentStoreInterface;
use NetFusion\Core\Collaboration\Document\DocumentSnapshot;
use NetFusion\Core\Collaboration\Ot\TextOperation;

class DatabaseDocumentStore implements DocumentStoreInterface
{
    protected PDO $pdo;
    protected string $docTable;
    protected string $opsTable;

    public function __construct(
        PDO|Connection $connection,
        string $docTable = 'netfusion_collab_documents',
        string $opsTable = 'netfusion_collab_operations'
    ) {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->docTable = $docTable;
        $this->opsTable = $opsTable;
    }

    public function load(string $docId): ?DocumentSnapshot
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->docTable} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return DocumentSnapshot::fromArray([
            'id' => $row['id'],
            'title' => $row['title'],
            'content' => $row['content'],
            'revision' => (int)$row['revision'],
            'author_id' => $row['author_id'] ?? null,
            'metadata' => isset($row['metadata']) ? (json_decode($row['metadata'], true) ?: []) : [],
            'updated_at' => $row['updated_at'],
        ]);
    }

    public function save(DocumentSnapshot $doc): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->docTable} (
            id, title, content, revision, author_id, metadata, created_at, updated_at
        ) VALUES (
            :id, :title, :content, :revision, :author_id, :metadata, :created_at, :updated_at
        ) ON CONFLICT(id) DO UPDATE SET
            title = excluded.title,
            content = excluded.content,
            revision = excluded.revision,
            metadata = excluded.metadata,
            updated_at = excluded.updated_at";

        // Fallback for MySQL/MariaDB syntax if ON CONFLICT fails (SQLite & PostgreSQL support ON CONFLICT)
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'id' => $doc->id,
                'title' => $doc->title,
                'content' => $doc->content,
                'revision' => $doc->revision,
                'author_id' => $doc->authorId,
                'metadata' => json_encode($doc->metadata),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable) {
            // Standard Update / Insert fallback
            $exists = $this->exists($doc->id);
            if ($exists) {
                $stmt = $this->pdo->prepare("UPDATE {$this->docTable} SET title = :title, content = :content, revision = :revision, metadata = :metadata, updated_at = :updated_at WHERE id = :id");
                return $stmt->execute([
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'content' => $doc->content,
                    'revision' => $doc->revision,
                    'metadata' => json_encode($doc->metadata),
                    'updated_at' => $now,
                ]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO {$this->docTable} (id, title, content, revision, author_id, metadata, created_at, updated_at) VALUES (:id, :title, :content, :revision, :author_id, :metadata, :created_at, :updated_at)");
                return $stmt->execute([
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'content' => $doc->content,
                    'revision' => $doc->revision,
                    'author_id' => $doc->authorId,
                    'metadata' => json_encode($doc->metadata),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function appendOperation(string $docId, int $revision, TextOperation $operation, string $userId): bool
    {
        $id = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("INSERT INTO {$this->opsTable} (
            id, document_id, revision, operation, user_id, created_at
        ) VALUES (
            :id, :document_id, :revision, :operation, :user_id, :created_at
        )");

        return $stmt->execute([
            'id' => $id,
            'document_id' => $docId,
            'revision' => $revision,
            'operation' => json_encode($operation->toArray()),
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    public function getOperationsSince(string $docId, int $fromRevision): array
    {
        $stmt = $this->pdo->prepare("SELECT revision, operation, user_id, created_at FROM {$this->opsTable} WHERE document_id = :doc_id AND revision > :from_rev ORDER BY revision ASC");
        $stmt->execute([
            'doc_id' => $docId,
            'from_rev' => $fromRevision,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $opData = json_decode($row['operation'], true) ?: [];
            $results[] = [
                'revision' => (int)$row['revision'],
                'operation' => TextOperation::fromArray($opData),
                'user_id' => $row['user_id'],
                'created_at' => $row['created_at'],
            ];
        }

        return $results;
    }

    public function exists(string $docId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->docTable} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $docId]);
        return (bool)$stmt->fetchColumn();
    }

    public function delete(string $docId): bool
    {
        $stmt1 = $this->pdo->prepare("DELETE FROM {$this->opsTable} WHERE document_id = :id");
        $stmt1->execute(['id' => $docId]);

        $stmt2 = $this->pdo->prepare("DELETE FROM {$this->docTable} WHERE id = :id");
        return $stmt2->execute(['id' => $docId]);
    }
}
