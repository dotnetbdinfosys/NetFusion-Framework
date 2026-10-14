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

namespace NetFusion\Core\CEP\Contracts;

interface CEPStoreInterface
{
    /**
     * Save/record a detected CEP alert or pattern match.
     *
     * @param array<string, mixed> $alertData
     */
    public function saveAlert(array $alertData): string;

    /**
     * Retrieve recorded alerts.
     *
     * @param array<string, mixed> $filter
     * @return array<array<string, mixed>>
     */
    public function getAlerts(array $filter = [], int $limit = 50, int $offset = 0): array;

    /**
     * Save window/NFA state snapshot for crash recovery.
     *
     * @param string $ruleName
     * @param string $partitionKey
     * @param array<string, mixed> $stateData
     */
    public function saveState(string $ruleName, string $partitionKey, array $stateData): void;

    /**
     * Load state snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function loadState(string $ruleName, string $partitionKey): ?array;

    /**
     * Clear state for a specific rule/partition or all.
     */
    public function clearState(?string $ruleName = null, ?string $partitionKey = null): void;

    /**
     * Record CEP engine execution metrics.
     *
     * @param string $metricName
     * @param float $value
     * @param array<string, mixed> $tags
     */
    public function recordMetric(string $metricName, float $value, array $tags = []): void;
}
