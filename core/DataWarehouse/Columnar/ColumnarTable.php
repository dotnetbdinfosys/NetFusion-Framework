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

namespace NetFusion\Core\DataWarehouse\Columnar;

use NetFusion\Core\DataWarehouse\Contracts\ColumnStoreInterface;

class ColumnarTable implements ColumnStoreInterface
{
    /**
     * @var array<int, ColumnChunk>
     */
    protected array $chunks = [];

    protected int $chunkCapacity;
    protected int $nextChunkId = 0;

    /**
     * @param string $name Table name
     * @param array<string, array{type: string, codec?: string}> $schema Column definitions
     * @param int $chunkCapacity
     */
    public function __construct(
        protected string $name,
        protected array $schema = [],
        int $chunkCapacity = 10000
    ) {
        $this->chunkCapacity = $chunkCapacity;
        $this->createNextChunk();
    }

    protected function createNextChunk(): ColumnChunk
    {
        $chunk = new ColumnChunk($this->nextChunkId++, $this->schema, $this->chunkCapacity);
        $this->chunks[] = $chunk;
        return $chunk;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function insert(array $row): void
    {
        $currentChunk = end($this->chunks);
        if ($currentChunk === false || $currentChunk->isFull()) {
            $currentChunk = $this->createNextChunk();
        }

        $currentChunk->appendRow($row);
    }

    public function insertBatch(array $rows): void
    {
        foreach ($rows as $row) {
            $this->insert($row);
        }
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->chunks as $chunk) {
            $total += $chunk->getRowCount();
        }
        return $total;
    }

    /**
     * @return array<int, ColumnChunk>
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * Execute vectorized columnar query with ZoneMap skip pruning and in-memory aggregation.
     *
     * @param array<string, array{operator: string, value: mixed}|mixed> $filters
     * @param array<string> $groupBy
     * @param array<string, string> $aggregations Measure => Function ('SUM', 'AVG', 'MIN', 'MAX', 'COUNT')
     * @return array<int, array<string, mixed>>
     */
    public function query(array $filters = [], array $groupBy = [], array $aggregations = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $groups = [];

        foreach ($this->chunks as $chunk) {
            // 1. Zone Map Pruning: check if entire chunk can be skipped
            if (!$chunk->canMatchFilters($normalizedFilters)) {
                continue; // Skip chunk!
            }

            $rowCount = $chunk->getRowCount();
            $vectors = $chunk->getVectors();

            // Extract only columns needed for filters, group by, and aggregations (Columnar projection)
            for ($rowIdx = 0; $rowIdx < $rowCount; $rowIdx++) {
                // Check row filter match
                if (!$this->rowMatchesFilters($vectors, $rowIdx, $normalizedFilters)) {
                    continue;
                }

                // Compute Group By Key
                $groupKeyParts = [];
                $groupData = [];
                foreach ($groupBy as $groupCol) {
                    $val = isset($vectors[$groupCol]) ? $vectors[$groupCol]->get($rowIdx) : null;
                    $groupKeyParts[] = $val ?? '__NULL__';
                    $groupData[$groupCol] = $val;
                }
                $groupKey = empty($groupKeyParts) ? '__GLOBAL__' : implode('||', $groupKeyParts);

                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'group_fields' => $groupData,
                        'aggregators' => [],
                        'count' => 0,
                    ];
                    foreach ($aggregations as $measureCol => $fn) {
                        $groups[$groupKey]['aggregators'][$measureCol] = [
                            'sum' => 0.0,
                            'count' => 0,
                            'min' => null,
                            'max' => null,
                            'fn' => strtoupper($fn),
                        ];
                    }
                }

                $groups[$groupKey]['count']++;

                // Accumulate Aggregators
                foreach ($aggregations as $measureCol => $fn) {
                    $val = isset($vectors[$measureCol]) ? (float)$vectors[$measureCol]->get($rowIdx) : 0.0;
                    $groups[$groupKey]['aggregators'][$measureCol]['sum'] += $val;
                    $groups[$groupKey]['aggregators'][$measureCol]['count']++;
                    if ($groups[$groupKey]['aggregators'][$measureCol]['min'] === null || $val < $groups[$groupKey]['aggregators'][$measureCol]['min']) {
                        $groups[$groupKey]['aggregators'][$measureCol]['min'] = $val;
                    }
                    if ($groups[$groupKey]['aggregators'][$measureCol]['max'] === null || $val > $groups[$groupKey]['aggregators'][$measureCol]['max']) {
                        $groups[$groupKey]['aggregators'][$measureCol]['max'] = $val;
                    }
                }
            }
        }

        // Format and finalize aggregated results
        $results = [];
        foreach ($groups as $groupKey => $group) {
            $rowResult = $group['group_fields'];
            foreach ($group['aggregators'] as $measureCol => $agg) {
                $fn = $agg['fn'];
                $resultVal = match ($fn) {
                    'SUM' => $agg['sum'],
                    'AVG', 'AVERAGE' => $agg['count'] > 0 ? round($agg['sum'] / $agg['count'], 4) : 0.0,
                    'MIN' => $agg['min'] ?? 0.0,
                    'MAX' => $agg['max'] ?? 0.0,
                    'COUNT' => $agg['count'],
                    default => $agg['sum'],
                };
                $aggSuffix = in_array($fn, ['AVG', 'AVERAGE'], true) ? 'avg' : strtolower($fn);
                $rowResult[$measureCol . '_' . $aggSuffix] = $resultVal;
            }
            if (empty($aggregations)) {
                $rowResult['row_count'] = $group['count'];
            }
            $results[] = $rowResult;
        }

        return $results;
    }

    protected function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $col => $spec) {
            if (is_array($spec) && isset($spec['operator'])) {
                $normalized[$col] = $spec;
            } else {
                $normalized[$col] = [
                    'operator' => '=',
                    'value' => $spec,
                ];
            }
        }
        return $normalized;
    }

    protected function rowMatchesFilters(array $vectors, int $rowIdx, array $filters): bool
    {
        foreach ($filters as $col => $filter) {
            if (!isset($vectors[$col])) {
                continue;
            }

            $val = $vectors[$col]->get($rowIdx);
            $op = strtoupper($filter['operator'] ?? '=');
            $target = $filter['value'] ?? null;

            $matched = match ($op) {
                '=', '==' => ($val == $target),
                '!=' , '<>' => ($val != $target),
                '>' => ($val > $target),
                '>=' => ($val >= $target),
                '<' => ($val < $target),
                '<=' => ($val <= $target),
                'IN' => is_array($target) && in_array($val, $target, false),
                'BETWEEN' => is_array($target) && count($target) >= 2 && ($val >= $target[0] && $val <= $target[1]),
                default => true,
            };

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    public function compressAll(): void
    {
        foreach ($this->chunks as $chunk) {
            $chunk->compress();
        }
    }
}
