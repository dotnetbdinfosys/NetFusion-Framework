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
 * Distributed SQL query planner resolving optimal partition routing and pushdown plans.
 */
class DistributedQueryPlanner
{
    /**
     * @var array<string, TablePartitionConfig>
     */
    private array $tableConfigs = [];

    private DistributedQueryParser $parser;

    public function __construct(array $tableConfigs = [])
    {
        $this->parser = new DistributedQueryParser();
        foreach ($tableConfigs as $config) {
            $this->addTableConfig($config);
        }
    }

    public function addTableConfig(TablePartitionConfig $config): self
    {
        $this->tableConfigs[strtolower($config->table)] = $config;
        return $this;
    }

    public function getTableConfig(string $table): ?TablePartitionConfig
    {
        return $this->tableConfigs[strtolower($table)] ?? null;
    }

    /**
     * Generate an optimized distributed QueryPlan.
     *
     * @param string $sql
     * @param array<mixed> $params
     * @return QueryPlan
     */
    public function plan(string $sql, array $params = []): QueryPlan
    {
        // Preliminary parse to discover table name
        $tempParsed = $this->parser->parse($sql, $params, null);
        $table = $tempParsed['table'];
        $tableConfig = $this->getTableConfig($table);
        $partitionColumn = $tableConfig?->partitionKey;

        // Re-parse with discovered partition column for precise key extraction
        $parsed = $this->parser->parse($sql, $params, $partitionColumn);

        $targetShards = [];
        $isScatterGather = true;

        if ($tableConfig === null) {
            // Default unpartitioned table: single default shard
            $targetShards = [new ShardDescriptor('shard_default', 'local', PartitionStrategy::HASH)];
            $isScatterGather = false;
        } else {
            $pkVal = $parsed['partition_key_value'];
            if ($pkVal !== null) {
                $targetShards = $tableConfig->getMatchingShards($pkVal);
                if (count($targetShards) === 1) {
                    $isScatterGather = false;
                }
            } else {
                $targetShards = array_values($tableConfig->getShards());
                $isScatterGather = (count($targetShards) > 1);
            }
        }

        // Construct pushdown SQL
        $pushdownSql = $this->generatePushdownSql($parsed);

        return new QueryPlan(
            type: $parsed['type'],
            table: $table,
            targetShards: $targetShards,
            isScatterGather: $isScatterGather,
            pushdownSql: $pushdownSql,
            pushdownParams: $params,
            aggregates: $parsed['aggregates'],
            orderBy: $parsed['order_by'],
            orderDir: $parsed['order_dir'],
            limit: $parsed['limit'],
            offset: $parsed['offset'],
            insertData: $parsed['insert_data'],
            updateData: $parsed['update_data'],
            where: $parsed['where']
        );
    }

    /**
     * Generate query string for pushing down to individual shard storage engines.
     * For scatter-gather with aggregates, rewrites AVG into partial SUM and COUNT.
     */
    private function generatePushdownSql(array $parsed): string
    {
        if ($parsed['type'] !== SqlNodeType::SELECT) {
            return $parsed['raw_sql'];
        }

        // Rewrite SELECT column expressions for shard pushdown
        $cols = [];
        if (!empty($parsed['aggregates'])) {
            foreach ($parsed['aggregates'] as $alias => $agg) {
                if ($agg['func'] === 'AVG') {
                    $cols[] = "SUM({$agg['column']}) AS sum_{$alias}";
                    $cols[] = "COUNT({$agg['column']}) AS count_{$alias}";
                } else {
                    $cols[] = "{$agg['func']}({$agg['column']}) AS {$alias}";
                }
            }
        } else {
            $cols = !empty($parsed['columns']) ? $parsed['columns'] : ['*'];
        }

        $sql = 'SELECT ' . implode(', ', $cols) . " FROM {$parsed['table']}";

        if (!empty($parsed['where'])) {
            $whereParts = [];
            foreach ($parsed['where'] as $w) {
                $valStr = is_numeric($w['value']) ? $w['value'] : "'" . addslashes((string) $w['value']) . "'";
                $whereParts[] = "{$w['column']} {$w['operator']} {$valStr}";
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        if ($parsed['order_by'] !== null) {
            $sql .= " ORDER BY {$parsed['order_by']} {$parsed['order_dir']}";
        }

        // Do NOT push down LIMIT / OFFSET during scatter-gather since global coordinator must re-page
        return $sql;
    }
}
