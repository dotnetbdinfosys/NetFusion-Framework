<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\DistributedDb\DistributedSql;

/**
 * Scatter-gather distributed query execution engine coordinating parallel shards,
 * merging partitioned streams, and resolving global aggregations.
 */
class ScatterGatherExecutor
{
    /**
     * @var array<string, callable> Custom shard execution handlers: fn(string $sql, array $params): array
     */
    private array $shardHandlers = [];

    /**
     * @var array<string, array<string, array<mixed>>> In-memory fallback shard tables: [$shardId][$table] = array of rows
     */
    private array $shardTables = [];

    public function registerShardHandler(string $shardId, callable $handler): self
    {
        $this->shardHandlers[$shardId] = $handler;
        return $this;
    }

    public function setShardTableData(string $shardId, string $table, array $rows): self
    {
        $this->shardTables[$shardId][strtolower($table)] = $rows;
        return $this;
    }

    public function getShardTableData(string $shardId, string $table): array
    {
        return $this->shardTables[$shardId][strtolower($table)] ?? [];
    }

    /**
     * Execute a distributed QueryPlan across target shards and assemble results.
     *
     * @param QueryPlan $plan
     * @return array<mixed>
     */
    public function execute(QueryPlan $plan): array
    {
        $table = strtolower($plan->table);

        if ($plan->type === SqlNodeType::INSERT) {
            $insertedCount = 0;
            foreach ($plan->targetShards as $shard) {
                if (isset($this->shardHandlers[$shard->shardId])) {
                    ($this->shardHandlers[$shard->shardId])($plan->pushdownSql, $plan->pushdownParams);
                } else {
                    $this->shardTables[$shard->shardId][$table][] = $plan->insertData;
                }
                $insertedCount++;
            }
            return ['affected_rows' => $insertedCount, 'shards' => $plan->getTargetShardIds()];
        }

        if ($plan->type === SqlNodeType::UPDATE) {
            $updatedCount = 0;
            foreach ($plan->targetShards as $shard) {
                if (isset($this->shardHandlers[$shard->shardId])) {
                    ($this->shardHandlers[$shard->shardId])($plan->pushdownSql, $plan->pushdownParams);
                } elseif (isset($this->shardTables[$shard->shardId][$table])) {
                    foreach ($this->shardTables[$shard->shardId][$table] as &$row) {
                        if ($this->matchesWhere($row, $plan->where)) {
                            $row = array_merge($row, $plan->updateData);
                            $updatedCount++;
                        }
                    }
                    unset($row);
                }
            }
            return ['affected_rows' => $updatedCount, 'shards' => $plan->getTargetShardIds()];
        }

        if ($plan->type === SqlNodeType::DELETE) {
            $deletedCount = 0;
            foreach ($plan->targetShards as $shard) {
                if (isset($this->shardHandlers[$shard->shardId])) {
                    ($this->shardHandlers[$shard->shardId])($plan->pushdownSql, $plan->pushdownParams);
                } elseif (isset($this->shardTables[$shard->shardId][$table])) {
                    $remaining = [];
                    foreach ($this->shardTables[$shard->shardId][$table] as $row) {
                        if ($this->matchesWhere($row, $plan->where)) {
                            $deletedCount++;
                        } else {
                            $remaining[] = $row;
                        }
                    }
                    $this->shardTables[$shard->shardId][$table] = $remaining;
                }
            }
            return ['affected_rows' => $deletedCount, 'shards' => $plan->getTargetShardIds()];
        }

        // SELECT query: Scatter to shards and gather results
        $partialResults = [];
        foreach ($plan->targetShards as $shard) {
            $shardRows = [];
            if (isset($this->shardHandlers[$shard->shardId])) {
                $shardRows = ($this->shardHandlers[$shard->shardId])($plan->pushdownSql, $plan->pushdownParams);
            } elseif (isset($this->shardTables[$shard->shardId][$table])) {
                $shardRows = $this->queryInMemoryShard($shard->shardId, $table, $plan);
            }

            $partialResults[$shard->shardId] = $shardRows;
        }

        // Global Aggregation if requested
        if (!empty($plan->aggregates)) {
            return $this->resolveGlobalAggregates($partialResults, $plan->aggregates);
        }

        // Combine rows from all shards
        $combinedRows = [];
        foreach ($partialResults as $rows) {
            foreach ($rows as $row) {
                $combinedRows[] = $row;
            }
        }

        // Global Ordering
        if ($plan->orderBy !== null) {
            $col = $plan->orderBy;
            $isDesc = (strtoupper($plan->orderDir) === 'DESC');
            usort($combinedRows, function ($a, $b) use ($col, $isDesc) {
                $valA = $a[$col] ?? null;
                $valB = $b[$col] ?? null;
                if ($valA == $valB) {
                    return 0;
                }
                $res = ($valA < $valB) ? -1 : 1;
                return $isDesc ? -$res : $res;
            });
        }

        // Global Paging (LIMIT & OFFSET)
        $offset = $plan->offset ?? 0;
        $limit = $plan->limit;
        if ($limit !== null || $offset > 0) {
            $combinedRows = array_slice($combinedRows, $offset, $limit);
        }

        return $combinedRows;
    }

    /**
     * Query rows from in-memory fallback shard store.
     */
    private function queryInMemoryShard(string $shardId, string $table, QueryPlan $plan): array
    {
        $allRows = $this->shardTables[$shardId][$table] ?? [];
        $filtered = [];

        foreach ($allRows as $row) {
            if ($this->matchesWhere($row, $plan->where)) {
                $filtered[] = $row;
            }
        }

        // If aggregates requested on this shard, compute partial aggregate row
        if (!empty($plan->aggregates)) {
            $partialRow = [];
            foreach ($plan->aggregates as $alias => $agg) {
                $func = $agg['func'];
                $col = $agg['column'];
                $vals = [];
                foreach ($filtered as $r) {
                    if ($col === '*' || isset($r[$col])) {
                        $vals[] = ($col === '*') ? 1 : $r[$col];
                    }
                }

                if ($func === 'COUNT') {
                    $partialRow[$alias] = count($vals);
                } elseif ($func === 'SUM') {
                    $partialRow[$alias] = array_sum($vals);
                } elseif ($func === 'AVG') {
                    $partialRow["sum_{$alias}"] = array_sum($vals);
                    $partialRow["count_{$alias}"] = count($vals);
                } elseif ($func === 'MIN') {
                    $partialRow[$alias] = !empty($vals) ? min($vals) : null;
                } elseif ($func === 'MAX') {
                    $partialRow[$alias] = !empty($vals) ? max($vals) : null;
                }
            }
            return [$partialRow];
        }

        return $filtered;
    }

    /**
     * Check if a row satisfies WHERE conditions.
     */
    private function matchesWhere(array $row, array $where): bool
    {
        foreach ($where as $w) {
            $col = $w['column'];
            $op = $w['operator'];
            $expected = $w['value'];
            $actual = $row[$col] ?? null;

            $matched = match ($op) {
                '=' => ($actual == $expected),
                '!=', '<>' => ($actual != $expected),
                '>' => ($actual > $expected),
                '>=' => ($actual >= $expected),
                '<' => ($actual < $expected),
                '<=' => ($actual <= $expected),
                'LIKE' => (str_contains(strtolower((string) $actual), strtolower(trim((string) $expected, '%')))),
                default => true,
            };

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve global aggregates across partial shard results.
     */
    private function resolveGlobalAggregates(array $partialResults, array $aggregates): array
    {
        $globalRow = [];

        foreach ($aggregates as $alias => $agg) {
            $func = $agg['func'];

            if ($func === 'COUNT') {
                $total = 0;
                foreach ($partialResults as $rows) {
                    if (!empty($rows[0][$alias])) {
                        $total += (int) $rows[0][$alias];
                    }
                }
                $globalRow[$alias] = $total;
            } elseif ($func === 'SUM') {
                $total = 0.0;
                foreach ($partialResults as $rows) {
                    if (!empty($rows[0][$alias])) {
                        $total += (float) $rows[0][$alias];
                    }
                }
                $globalRow[$alias] = $total;
            } elseif ($func === 'AVG') {
                $globalSum = 0.0;
                $globalCount = 0;
                foreach ($partialResults as $rows) {
                    if (isset($rows[0]["sum_{$alias}"])) {
                        $globalSum += (float) $rows[0]["sum_{$alias}"];
                    }
                    if (isset($rows[0]["count_{$alias}"])) {
                        $globalCount += (int) $rows[0]["count_{$alias}"];
                    }
                }
                $globalRow[$alias] = ($globalCount > 0) ? ($globalSum / $globalCount) : 0.0;
            } elseif ($func === 'MIN') {
                $mins = [];
                foreach ($partialResults as $rows) {
                    if (isset($rows[0][$alias]) && $rows[0][$alias] !== null) {
                        $mins[] = $rows[0][$alias];
                    }
                }
                $globalRow[$alias] = !empty($mins) ? min($mins) : null;
            } elseif ($func === 'MAX') {
                $maxs = [];
                foreach ($partialResults as $rows) {
                    if (isset($rows[0][$alias]) && $rows[0][$alias] !== null) {
                        $maxs[] = $rows[0][$alias];
                    }
                }
                $globalRow[$alias] = !empty($maxs) ? max($maxs) : null;
            }
        }

        return [$globalRow];
    }
}
