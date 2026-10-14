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

namespace NetFusion\Core\RuleEngine\Store;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\RuleEngine\Contracts\RuleStoreInterface;
use PDO;

class DatabaseRuleStore implements RuleStoreInterface
{
    protected PDO $pdo;
    protected string $rulesTable;
    protected string $tablesTable;
    protected string $auditTable;

    public function __construct(
        PDO|Connection $connection,
        string $rulesTable = 'nf_rules',
        string $tablesTable = 'nf_decision_tables',
        string $auditTable = 'nf_rule_audit_logs'
    ) {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->rulesTable = $rulesTable;
        $this->tablesTable = $tablesTable;
        $this->auditTable = $auditTable;
    }

    public function saveRule(string $name, array $ruleData): void
    {
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO {$this->rulesTable} (name, description, salience, patterns_json, enabled, metadata_json, created_at, updated_at)
            VALUES (:name, :description, :salience, :patterns_json, :enabled, :metadata_json, :created_at, :updated_at)
        ");

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            ':name' => $name,
            ':description' => $ruleData['description'] ?? '',
            ':salience' => (int) ($ruleData['salience'] ?? 0),
            ':patterns_json' => json_encode($ruleData['patterns'] ?? []),
            ':enabled' => ($ruleData['enabled'] ?? true) ? 1 : 0,
            ':metadata_json' => json_encode($ruleData['metadata'] ?? []),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function getRule(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->rulesTable} WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'name' => $row['name'],
            'description' => $row['description'] ?? '',
            'salience' => (int) $row['salience'],
            'patterns' => json_decode($row['patterns_json'] ?? '[]', true),
            'enabled' => (bool) $row['enabled'],
            'metadata' => json_decode($row['metadata_json'] ?? '{}', true),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    public function getAllRules(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->rulesTable} ORDER BY salience DESC, name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rules = [];
        foreach ($rows as $row) {
            $rules[$row['name']] = [
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'salience' => (int) $row['salience'],
                'patterns' => json_decode($row['patterns_json'] ?? '[]', true),
                'enabled' => (bool) $row['enabled'],
                'metadata' => json_decode($row['metadata_json'] ?? '{}', true),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $rules;
    }

    public function deleteRule(string $name): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->rulesTable} WHERE name = :name");
        $stmt->execute([':name' => $name]);
        return $stmt->rowCount() > 0;
    }

    public function saveDecisionTable(string $name, array $tableData): void
    {
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO {$this->tablesTable} (name, hit_policy, inputs_json, outputs_json, rules_json, created_at, updated_at)
            VALUES (:name, :hit_policy, :inputs_json, :outputs_json, :rules_json, :created_at, :updated_at)
        ");

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            ':name' => $name,
            ':hit_policy' => $tableData['hit_policy'] ?? $tableData['hitPolicy'] ?? 'FIRST',
            ':inputs_json' => json_encode($tableData['inputs'] ?? []),
            ':outputs_json' => json_encode($tableData['outputs'] ?? []),
            ':rules_json' => json_encode($tableData['rules'] ?? []),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function getDecisionTable(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tablesTable} WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'name' => $row['name'],
            'hit_policy' => $row['hit_policy'],
            'inputs' => json_decode($row['inputs_json'] ?? '[]', true),
            'outputs' => json_decode($row['outputs_json'] ?? '[]', true),
            'rules' => json_decode($row['rules_json'] ?? '[]', true),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    public function getAllDecisionTables(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->tablesTable} ORDER BY name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tables = [];
        foreach ($rows as $row) {
            $tables[$row['name']] = [
                'name' => $row['name'],
                'hit_policy' => $row['hit_policy'],
                'inputs' => json_decode($row['inputs_json'] ?? '[]', true),
                'outputs' => json_decode($row['outputs_json'] ?? '[]', true),
                'rules' => json_decode($row['rules_json'] ?? '[]', true),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $tables;
    }

    public function logAudit(array $logData): string
    {
        $id = 'audit_' . bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->auditTable} (id, rule_name, decision_table, facts_snapshot_json, result_json, execution_time_ms, created_at)
            VALUES (:id, :rule_name, :decision_table, :facts_snapshot_json, :result_json, :execution_time_ms, :created_at)
        ");

        $stmt->execute([
            ':id' => $id,
            ':rule_name' => $logData['rule_name'] ?? null,
            ':decision_table' => $logData['decision_table'] ?? null,
            ':facts_snapshot_json' => json_encode($logData['facts'] ?? []),
            ':result_json' => json_encode($logData['result'] ?? null),
            ':execution_time_ms' => (float) ($logData['execution_time_ms'] ?? 0.0),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function getAuditLogs(array $filters = [], int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->auditTable} WHERE 1=1";
        $params = [];

        if (!empty($filters['rule_name'])) {
            $sql .= " AND rule_name = :rule_name";
            $params[':rule_name'] = $filters['rule_name'];
        }
        if (!empty($filters['decision_table'])) {
            $sql .= " AND decision_table = :decision_table";
            $params[':decision_table'] = $filters['decision_table'];
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return [
                'id' => $row['id'],
                'rule_name' => $row['rule_name'],
                'decision_table' => $row['decision_table'],
                'facts' => json_decode($row['facts_snapshot_json'] ?? '[]', true),
                'result' => json_decode($row['result_json'] ?? 'null', true),
                'execution_time_ms' => (float) $row['execution_time_ms'],
                'created_at' => $row['created_at'],
            ];
        }, $rows);
    }
}
