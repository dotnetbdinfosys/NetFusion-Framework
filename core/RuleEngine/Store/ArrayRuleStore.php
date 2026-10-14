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

use NetFusion\Core\RuleEngine\Contracts\RuleStoreInterface;

class ArrayRuleStore implements RuleStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    protected array $rules = [];
    /** @var array<string, array<string, mixed>> */
    protected array $decisionTables = [];
    /** @var array<array<string, mixed>> */
    protected array $auditLogs = [];

    public function saveRule(string $name, array $ruleData): void
    {
        $this->rules[$name] = array_merge($ruleData, [
            'name' => $name,
            'updated_at' => microtime(true),
        ]);
    }

    public function getRule(string $name): ?array
    {
        return $this->rules[$name] ?? null;
    }

    public function getAllRules(): array
    {
        return $this->rules;
    }

    public function deleteRule(string $name): bool
    {
        if (isset($this->rules[$name])) {
            unset($this->rules[$name]);
            return true;
        }
        return false;
    }

    public function saveDecisionTable(string $name, array $tableData): void
    {
        $this->decisionTables[$name] = array_merge($tableData, [
            'name' => $name,
            'updated_at' => microtime(true),
        ]);
    }

    public function getDecisionTable(string $name): ?array
    {
        return $this->decisionTables[$name] ?? null;
    }

    public function getAllDecisionTables(): array
    {
        return $this->decisionTables;
    }

    public function logAudit(array $logData): string
    {
        $id = 'audit_' . bin2hex(random_bytes(8));
        $record = array_merge($logData, [
            'id' => $id,
            'created_at' => microtime(true),
        ]);
        $this->auditLogs[] = $record;
        return $id;
    }

    public function getAuditLogs(array $filters = [], int $limit = 50): array
    {
        $results = $this->auditLogs;

        if (isset($filters['rule_name'])) {
            $results = array_filter($results, fn($r) => ($r['rule_name'] ?? null) === $filters['rule_name']);
        }
        if (isset($filters['decision_table'])) {
            $results = array_filter($results, fn($r) => ($r['decision_table'] ?? null) === $filters['decision_table']);
        }

        return array_slice(array_reverse($results), 0, $limit);
    }
}
