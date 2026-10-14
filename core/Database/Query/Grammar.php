<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Query/Grammar.php
| Module    : Core\Database\Query
| Namespace : NetFusion\Core\Database\Query
| Purpose   : SQL dialect compiler for MySQL, PostgreSQL, and SQLite quoting and clause formatting.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Query/Grammar.php
| Module    : Core\Database\Query
| Namespace : NetFusion\Core\Database\Query
| Purpose   : SQL dialect compiler for MySQL, PostgreSQL, and SQLite quoting and clause formatting.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Query/Grammar.php
| Module    : Core\Database\Query
| Namespace : NetFusion\Core\Database\Query
| Purpose   : SQL dialect compiler for MySQL, PostgreSQL, and SQLite quoting and clause formatting.
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

namespace NetFusion\Core\Database\Query;

/**
 * NetFusion SQL Query Grammar
 *
 * Compiles fluent query builder structures into sanitized SQL strings.
 * Supports MySQL, PostgreSQL, and SQLite quoting conventions.
 *
 * @package NetFusion\Core\Database\Query
 * @version 1.0.0
 */
class Grammar
{
    /**
     * @param string $driver Driver identifier ('mysql', 'sqlite', 'pgsql').
     */
    public function __construct(
        protected readonly string $driver = 'mysql'
    ) {}

    /**
     * Wrap a table or column name in proper identifier delimiters.
     */
    public function wrap(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        // Handle table.column syntax
        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            return implode('.', array_map([$this, 'wrap'], $parts));
        }

        // Handle "column as alias" syntax
        if (stripos($value, ' as ') !== false) {
            [$original, $alias] = preg_split('/\s+as\s+/i', $value);
            return $this->wrap($original) . ' AS ' . $this->wrap($alias);
        }

        // Functions or raw expressions containing parentheses should not be wrapped as column names
        if (str_contains($value, '(') || str_contains($value, ')')) {
            return $value;
        }

        return match ($this->driver) {
            'pgsql'  => '"' . str_replace('"', '""', $value) . '"',
            default  => '`' . str_replace('`', '``', $value) . '`',
        };
    }

    /**
     * Compile a full SELECT statement.
     *
     * @param Builder $builder
     * @return string
     */
    public function compileSelect(Builder $builder): string
    {
        $columns = $this->compileColumns($builder);
        $from    = $this->compileFrom($builder);
        $joins   = $this->compileJoins($builder);
        $wheres  = $this->compileWheres($builder);
        $orders  = $this->compileOrders($builder);
        $limit   = $this->compileLimit($builder);

        $sql = "SELECT {$columns} FROM {$from}";

        if ($joins !== '') {
            $sql .= " {$joins}";
        }
        if ($wheres !== '') {
            $sql .= " {$wheres}";
        }
        if ($orders !== '') {
            $sql .= " {$orders}";
        }
        if ($limit !== '') {
            $sql .= " {$limit}";
        }

        return $sql;
    }

    /**
     * Compile columns list.
     */
    protected function compileColumns(Builder $builder): string
    {
        $columns = $builder->getColumns();
        if (empty($columns)) {
            return '*';
        }

        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Compile FROM table.
     */
    protected function compileFrom(Builder $builder): string
    {
        return $this->wrap($builder->getTable());
    }

    /**
     * Compile JOIN clauses.
     */
    protected function compileJoins(Builder $builder): string
    {
        $joins = $builder->getJoins();
        if (empty($joins)) {
            return '';
        }

        $sql = [];
        foreach ($joins as $join) {
            $type     = strtoupper($join['type']);
            $table    = $this->wrap($join['table']);
            $first    = $this->wrap($join['first']);
            $operator = $join['operator'];
            $second   = $this->wrap($join['second']);

            $sql[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile WHERE clauses.
     */
    public function compileWheres(Builder $builder): string
    {
        $wheres = $builder->getWheres();
        if (empty($wheres)) {
            return '';
        }

        $sql = [];
        foreach ($wheres as $i => $where) {
            $boolean = ($i === 0) ? '' : strtoupper($where['boolean']) . ' ';
            $column  = $this->wrap($where['column']);

            if ($where['type'] === 'basic') {
                $sql[] = "{$boolean}{$column} {$where['operator']} ?";
            } elseif ($where['type'] === 'in') {
                $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                $not = $where['not'] ? 'NOT ' : '';
                $sql[] = "{$boolean}{$column} {$not}IN ({$placeholders})";
            } elseif ($where['type'] === 'null') {
                $not = $where['not'] ? 'NOT ' : '';
                $sql[] = "{$boolean}{$column} IS {$not}NULL";
            } elseif ($where['type'] === 'raw') {
                $sql[] = "{$boolean}{$where['sql']}";
            }
        }

        return 'WHERE ' . implode(' ', $sql);
    }

    /**
     * Compile ORDER BY clauses.
     */
    protected function compileOrders(Builder $builder): string
    {
        $orders = $builder->getOrders();
        if (empty($orders)) {
            return '';
        }

        $sql = [];
        foreach ($orders as $order) {
            $sql[] = $this->wrap($order['column']) . ' ' . strtoupper($order['direction']);
        }

        return 'ORDER BY ' . implode(', ', $sql);
    }

    /**
     * Compile LIMIT and OFFSET.
     */
    protected function compileLimit(Builder $builder): string
    {
        $limit  = $builder->getLimit();
        $offset = $builder->getOffset();

        if ($limit === null) {
            return '';
        }

        $sql = "LIMIT {$limit}";
        if ($offset !== null && $offset > 0) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    /**
     * Compile an INSERT query.
     *
     * @param  Builder              $builder
     * @param  array<string, mixed> $values
     * @return string
     */
    public function compileInsert(Builder $builder, array $values): string
    {
        $table   = $this->wrap($builder->getTable());
        $columns = implode(', ', array_map([$this, 'wrap'], array_keys($values)));
        $holders = implode(', ', array_fill(0, count($values), '?'));

        return "INSERT INTO {$table} ({$columns}) VALUES ({$holders})";
    }

    /**
     * Compile an UPDATE query.
     *
     * @param  Builder              $builder
     * @param  array<string, mixed> $values
     * @return string
     */
    public function compileUpdate(Builder $builder, array $values): string
    {
        $table   = $this->wrap($builder->getTable());
        $columns = [];

        foreach (array_keys($values) as $key) {
            $columns[] = $this->wrap($key) . ' = ?';
        }

        $setClause = implode(', ', $columns);
        $wheres    = $this->compileWheres($builder);

        $sql = "UPDATE {$table} SET {$setClause}";
        if ($wheres !== '') {
            $sql .= " {$wheres}";
        }

        return $sql;
    }

    /**
     * Compile a DELETE query.
     *
     * @param  Builder $builder
     * @return string
     */
    public function compileDelete(Builder $builder): string
    {
        $table  = $this->wrap($builder->getTable());
        $wheres = $this->compileWheres($builder);

        $sql = "DELETE FROM {$table}";
        if ($wheres !== '') {
            $sql .= " {$wheres}";
        }

        return $sql;
    }
}
