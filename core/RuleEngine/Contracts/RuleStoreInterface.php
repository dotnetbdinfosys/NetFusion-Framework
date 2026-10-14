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

namespace NetFusion\Core\RuleEngine\Contracts;

interface RuleStoreInterface
{
    /**
     * Save or update a rule definition.
     *
     * @param array<string, mixed> $ruleData
     */
    public function saveRule(string $name, array $ruleData): void;

    /**
     * Get a rule definition by name.
     *
     * @return array<string, mixed>|null
     */
    public function getRule(string $name): ?array;

    /**
     * Get all registered rules.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllRules(): array;

    /**
     * Delete a rule by name.
     */
    public function deleteRule(string $name): bool;

    /**
     * Save a decision table definition.
     *
     * @param array<string, mixed> $tableData
     */
    public function saveDecisionTable(string $name, array $tableData): void;

    /**
     * Get a decision table definition by name.
     *
     * @return array<string, mixed>|null
     */
    public function getDecisionTable(string $name): ?array;

    /**
     * Get all decision tables.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllDecisionTables(): array;

    /**
     * Log a rule/decision execution audit trail record.
     *
     * @param array<string, mixed> $logData
     */
    public function logAudit(array $logData): string;

    /**
     * Get audit trail logs.
     *
     * @param array<string, mixed> $filters
     * @return array<array<string, mixed>>
     */
    public function getAuditLogs(array $filters = [], int $limit = 50): array;
}
