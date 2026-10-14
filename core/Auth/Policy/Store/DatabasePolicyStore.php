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

use NetFusion\Core\Auth\Policy\Contracts\PolicyStoreInterface;
use NetFusion\Core\Auth\Policy\Effect;
use NetFusion\Core\Auth\Policy\Policy;
use NetFusion\Core\Auth\Policy\Rule;
use NetFusion\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;

class DatabasePolicyStore implements PolicyStoreInterface
{
    protected string $table = 'netfusion_policies';

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

    public function save(Policy $policy): void
    {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $jsonRules = json_encode(array_map(fn(Rule $r) => $r->toArray(), $policy->getRules()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("
            INSERT INTO {$this->table} (id, name, priority, enabled, description, rules, created_at, updated_at)
            VALUES (:id, :name, :priority, :enabled, :description, :rules, :created_at, :updated_at)
            ON CONFLICT(id) DO UPDATE SET
                name = excluded.name,
                priority = excluded.priority,
                enabled = excluded.enabled,
                description = excluded.description,
                rules = excluded.rules,
                updated_at = excluded.updated_at
        ");

        $params = [
            ':id' => $policy->id,
            ':name' => $policy->name,
            ':priority' => $policy->priority,
            ':enabled' => $policy->enabled ? 1 : 0,
            ':description' => $policy->description,
            ':rules' => $jsonRules,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];

        try {
            $stmt->execute($params);
        } catch (\Throwable) {
            // MySQL fallback
            $stmtFallback = $pdo->prepare("
                INSERT INTO {$this->table} (id, name, priority, enabled, description, rules, created_at, updated_at)
                VALUES (:id, :name, :priority, :enabled, :description, :rules, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    priority = VALUES(priority),
                    enabled = VALUES(enabled),
                    description = VALUES(description),
                    rules = VALUES(rules),
                    updated_at = VALUES(updated_at)
            ");
            $stmtFallback->execute($params);
        }
    }

    public function find(string $id): ?Policy
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function get(string $id): ?Policy
    {
        return $this->find($id);
    }

    public function has(string $id): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT 1 FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    public function all(): array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT * FROM {$this->table} WHERE enabled = 1 ORDER BY priority DESC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $policies = [];
        foreach ($rows as $row) {
            $policies[] = $this->hydrate($row);
        }

        return $policies;
    }

    public function delete(string $id): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    protected function hydrate(array $row): Policy
    {
        $rawRules = json_decode((string)($row['rules'] ?? '[]'), true) ?? [];
        $rules = [];

        foreach ($rawRules as $rData) {
            $rules[] = new Rule(
                id: (string)($rData['id'] ?? uniqid('r_')),
                effect: Effect::from((string)($rData['effect'] ?? 'ALLOW')),
                actions: $rData['actions'] ?? '*',
                resources: $rData['resources'] ?? '*',
                conditions: $rData['conditions'] ?? null,
                description: $rData['description'] ?? null
            );
        }

        return new Policy(
            id: (string)$row['id'],
            name: (string)$row['name'],
            rules: $rules,
            priority: (int)$row['priority'],
            enabled: (bool)$row['enabled'],
            description: $row['description'] !== null ? (string)$row['description'] : null
        );
    }
}
