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

namespace NetFusion\Core\Auth\Policy\Store;

use NetFusion\Core\Auth\Policy\Contracts\RebacStoreInterface;
use NetFusion\Core\Auth\Policy\Rebac\RebacTuple;
use NetFusion\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;

class DatabaseRebacStore implements RebacStoreInterface
{
    protected string $table = 'netfusion_rebac_tuples';

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

        throw new RuntimeException("Unable to resolve PDO instance from database connection.");
    }

    public function write(RebacTuple $tuple): void
    {
        if ($this->exists($tuple)) {
            return;
        }

        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO {$this->table} (entity_type, entity_id, relation, subject_type, subject_id, subject_relation, created_at)
            VALUES (:entity_type, :entity_id, :relation, :subject_type, :subject_id, :subject_relation, :created_at)
        ");

        $params = [
            ':entity_type' => $tuple->entityType,
            ':entity_id' => $tuple->entityId,
            ':relation' => $tuple->relation,
            ':subject_type' => $tuple->subjectType,
            ':subject_id' => $tuple->subjectId,
            ':subject_relation' => $tuple->subjectRelation ?? '',
            ':created_at' => $now,
        ];

        try {
            $stmt->execute($params);
        } catch (\Throwable) {
            // Already inserted concurrently
        }
    }

    public function delete(RebacTuple $tuple): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("
            DELETE FROM {$this->table}
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
              AND relation = :relation
              AND subject_type = :subject_type
              AND subject_id = :subject_id
              AND subject_relation = :subject_relation
        ");

        $stmt->execute([
            ':entity_type' => $tuple->entityType,
            ':entity_id' => $tuple->entityId,
            ':relation' => $tuple->relation,
            ':subject_type' => $tuple->subjectType,
            ':subject_id' => $tuple->subjectId,
            ':subject_relation' => $tuple->subjectRelation ?? '',
        ]);
    }

    public function read(
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $relation = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): array {
        $pdo = $this->getPdo();
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($entityType !== null) {
            $query .= " AND entity_type = :entity_type";
            $params[':entity_type'] = $entityType;
        }
        if ($entityId !== null) {
            $query .= " AND entity_id = :entity_id";
            $params[':entity_id'] = $entityId;
        }
        if ($relation !== null) {
            $query .= " AND relation = :relation";
            $params[':relation'] = $relation;
        }
        if ($subjectType !== null) {
            $query .= " AND subject_type = :subject_type";
            $params[':subject_type'] = $subjectType;
        }
        if ($subjectId !== null) {
            $query .= " AND subject_id = :subject_id";
            $params[':subject_id'] = $subjectId;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tuples = [];
        foreach ($rows as $row) {
            $subRel = (string)($row['subject_relation'] ?? '');
            $tuples[] = new RebacTuple(
                entityType: (string)$row['entity_type'],
                entityId: (string)$row['entity_id'],
                relation: (string)$row['relation'],
                subjectType: (string)$row['subject_type'],
                subjectId: (string)$row['subject_id'],
                subjectRelation: $subRel !== '' ? $subRel : null
            );
        }

        return $tuples;
    }

    public function exists(RebacTuple $tuple): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("
            SELECT 1 FROM {$this->table}
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
              AND relation = :relation
              AND subject_type = :subject_type
              AND subject_id = :subject_id
              AND subject_relation = :subject_relation
        ");

        $stmt->execute([
            ':entity_type' => $tuple->entityType,
            ':entity_id' => $tuple->entityId,
            ':relation' => $tuple->relation,
            ':subject_type' => $tuple->subjectType,
            ':subject_id' => $tuple->subjectId,
            ':subject_relation' => $tuple->subjectRelation ?? '',
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public function find(array $criteria = []): array
    {
        return $this->read(
            entityType: $criteria['entity_type'] ?? null,
            entityId: $criteria['entity_id'] ?? null,
            relation: $criteria['relation'] ?? null,
            subjectType: $criteria['subject_type'] ?? null,
            subjectId: $criteria['subject_id'] ?? null
        );
    }

    public function clear(): void
    {
        $pdo = $this->getPdo();
        $pdo->exec("DELETE FROM {$this->table}");
    }
}
