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
 * Pure PHP SQL parser extracting distributed query metadata, predicates, and aggregates.
 */
class DistributedQueryParser
{
    /**
     * Parse SQL query string into structured metadata.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @param string|null $partitionColumn
     * @return array{
     *     type: SqlNodeType,
     *     table: string,
     *     columns: array<string>,
     *     aggregates: array<string, array{func: string, column: string, alias: string}>,
     *     where: array<array{column: string, operator: string, value: mixed}>,
     *     partition_key_value: mixed,
     *     insert_data: array<string, mixed>,
     *     update_data: array<string, mixed>,
     *     order_by: string|null,
     *     order_dir: string,
     *     limit: int|null,
     *     offset: int|null,
     *     raw_sql: string
     * }
     */
    public function parse(string $sql, array $params = [], ?string $partitionColumn = null): array
    {
        $trimmed = trim($sql);
        $upper = strtoupper($trimmed);

        $type = SqlNodeType::SELECT;
        if (str_starts_with($upper, 'INSERT')) {
            $type = SqlNodeType::INSERT;
        } elseif (str_starts_with($upper, 'UPDATE')) {
            $type = SqlNodeType::UPDATE;
        } elseif (str_starts_with($upper, 'DELETE')) {
            $type = SqlNodeType::DELETE;
        }

        $table = '';
        $columns = ['*'];
        $aggregates = [];
        $where = [];
        $partitionKeyValue = null;
        $insertData = [];
        $updateData = [];
        $orderBy = null;
        $orderDir = 'ASC';
        $limit = null;
        $offset = null;

        if ($type === SqlNodeType::SELECT) {
            // Extract table from FROM <table>
            if (preg_match('/\bFROM\s+([`"\']?\w+[`"\']?)/i', $trimmed, $m)) {
                $table = trim($m[1], '`"\'');
            }

            // Extract columns between SELECT and FROM
            if (preg_match('/^SELECT\s+(.+?)\s+FROM\b/is', $trimmed, $m)) {
                $rawCols = explode(',', $m[1]);
                $columns = [];
                foreach ($rawCols as $col) {
                    $col = trim($col);
                    // Check for aggregate functions: COUNT, SUM, AVG, MIN, MAX
                    if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\s*\(\s*([*a-zA-Z0-9_`"\']+)\s*\)(?:\s+(?:AS\s+)?([a-zA-Z0-9_]+))?/i', $col, $agg)) {
                        $func = strtoupper($agg[1]);
                        $arg = trim($agg[2], '`"\'');
                        $alias = !empty($agg[3]) ? trim($agg[3], '`"\'') : strtolower($func . '_' . str_replace('*', 'all', $arg));
                        $aggregates[$alias] = [
                            'func' => $func,
                            'column' => $arg,
                            'alias' => $alias,
                        ];
                        $columns[] = $alias;
                    } else {
                        $colName = trim($col, '`"\'');
                        if (preg_match('/(?:\s+(?:AS\s+)?([a-zA-Z0-9_]+))$/i', $col, $aliasMatch)) {
                            $colName = $aliasMatch[1];
                        }
                        $columns[] = $colName;
                    }
                }
            }
        } elseif ($type === SqlNodeType::INSERT) {
            // INSERT INTO <table> (col1, col2) VALUES (val1, val2)
            if (preg_match('/\bINSERT\s+INTO\s+([`"\']?\w+[`"\']?)\s*\((.+?)\)\s*VALUES\s*\((.+?)\)/is', $trimmed, $m)) {
                $table = trim($m[1], '`"\'');
                $colNames = array_map(fn($c) => trim($c, " `\"'\t\n\r"), explode(',', $m[2]));
                $valTokens = array_map(fn($v) => trim($v, " `\"'\t\n\r"), explode(',', $m[3]));

                $paramIdx = 0;
                foreach ($colNames as $i => $colName) {
                    $rawVal = $valTokens[$i] ?? null;
                    if ($rawVal === '?') {
                        $val = $params[$paramIdx++] ?? null;
                    } elseif (isset($params[$rawVal])) {
                        $val = $params[$rawVal];
                    } elseif (is_numeric($rawVal)) {
                        $val = str_contains($rawVal, '.') ? (float) $rawVal : (int) $rawVal;
                    } else {
                        $val = $rawVal;
                    }

                    $insertData[$colName] = $val;
                    if ($partitionColumn !== null && strcasecmp($colName, $partitionColumn) === 0) {
                        $partitionKeyValue = $val;
                    }
                }
            }
        } elseif ($type === SqlNodeType::UPDATE) {
            // UPDATE <table> SET col1 = val1, col2 = val2 WHERE ...
            if (preg_match('/\bUPDATE\s+([`"\']?\w+[`"\']?)\s+SET\s+(.+?)(?:\s+WHERE\b|$)/is', $trimmed, $m)) {
                $table = trim($m[1], '`"\'');
                $setPairs = explode(',', $m[2]);
                foreach ($setPairs as $pair) {
                    $parts = explode('=', $pair, 2);
                    if (count($parts) === 2) {
                        $col = trim($parts[0], " `\"'\t\n\r");
                        $val = trim($parts[1], " `\"'\t\n\r");
                        if (is_numeric($val)) {
                            $val = str_contains($val, '.') ? (float) $val : (int) $val;
                        }
                        $updateData[$col] = $val;
                    }
                }
            }
        } elseif ($type === SqlNodeType::DELETE) {
            // DELETE FROM <table> WHERE ...
            if (preg_match('/\bDELETE\s+FROM\s+([`"\']?\w+[`"\']?)/i', $trimmed, $m)) {
                $table = trim($m[1], '`"\'');
            }
        }

        // Extract WHERE predicates
        if (preg_match('/\bWHERE\s+(.+?)(?:\s+(?:GROUP\s+BY|ORDER\s+BY|LIMIT)\b|$)/is', $trimmed, $m)) {
            $whereClause = $m[1];
            // Split by AND for simple conditions
            $conditions = preg_split('/\s+AND\s+/i', $whereClause);
            foreach ($conditions as $cond) {
                if (preg_match('/([`"\']?\w+[`"\']?)\s*(=|!=|<>|>=|<=|>|<|LIKE)\s*(.+)/i', trim($cond), $cm)) {
                    $col = trim($cm[1], '`"\'');
                    $op = strtoupper(trim($cm[2]));
                    $valRaw = trim($cm[3], " `\"'\t\n\r");

                    $val = $valRaw;
                    if ($valRaw === '?') {
                        $val = array_shift($params);
                    } elseif (is_numeric($valRaw)) {
                        $val = str_contains($valRaw, '.') ? (float) $valRaw : (int) $valRaw;
                    }

                    $where[] = [
                        'column' => $col,
                        'operator' => $op,
                        'value' => $val,
                    ];

                    if ($partitionColumn !== null && strcasecmp($col, $partitionColumn) === 0 && $op === '=') {
                        $partitionKeyValue = $val;
                    }
                }
            }
        }

        // Extract ORDER BY
        if (preg_match('/\bORDER\s+BY\s+([`"\']?\w+[`"\']?)(?:\s+(ASC|DESC))?/i', $trimmed, $m)) {
            $orderBy = trim($m[1], '`"\'');
            $orderDir = !empty($m[2]) ? strtoupper($m[2]) : 'ASC';
        }

        // Extract LIMIT and OFFSET
        if (preg_match('/\bLIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i', $trimmed, $m)) {
            $limit = (int) $m[1];
            if (!empty($m[2])) {
                $offset = (int) $m[2];
            }
        } elseif (preg_match('/\bLIMIT\s+(\d+)\s*,\s*(\d+)/i', $trimmed, $m)) {
            // LIMIT offset, limit
            $offset = (int) $m[1];
            $limit = (int) $m[2];
        }

        return [
            'type' => $type,
            'table' => $table,
            'columns' => $columns,
            'aggregates' => $aggregates,
            'where' => $where,
            'partition_key_value' => $partitionKeyValue,
            'insert_data' => $insertData,
            'update_data' => $updateData,
            'order_by' => $orderBy,
            'order_dir' => $orderDir,
            'limit' => $limit,
            'offset' => $offset,
            'raw_sql' => $trimmed,
        ];
    }
}
