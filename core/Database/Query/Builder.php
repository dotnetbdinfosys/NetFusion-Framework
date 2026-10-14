<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Query/Builder.php
| Module    : Core\Database\Query
| Namespace : NetFusion\Core\Database\Query
| Purpose   : Fluent, chainable Query Builder with parameterized SQL execution and pagination.
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
| File      : core/Database/Query/Builder.php
| Module    : Core\Database\Query
| Namespace : NetFusion\Core\Database\Query
| Purpose   : Fluent, chainable Query Builder with parameterized SQL execution and pagination.
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
| File      : core/Database/Query/Builder.php
| Module    : Core\Database\Query
| Namespace : NetFusion\Core\Database\Query
| Purpose   : Fluent, chainable Query Builder with parameterized SQL execution and pagination.
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

use NetFusion\Core\Database\Connection;

/**
 * NetFusion Fluent Query Builder
 *
 * Chainable query builder with parameter binding for maximum security
 * against SQL Injection attacks.
 *
 * @package NetFusion\Core\Database\Query
 * @version 1.0.0
 */
class Builder
{
    /**
     * Target database table.
     */
    protected string $table = '';

    /**
     * Selected column list.
     *
     * @var string[]
     */
    protected array $columns = [];

    /**
     * Where conditions.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $wheres = [];

    /**
     * Parameter bindings for prepared statement execution.
     *
     * @var array<int, mixed>
     */
    protected array $bindings = [];

    /**
     * Joins clauses.
     *
     * @var array<int, array<string, string>>
     */
    protected array $joins = [];

    /**
     * Ordering specifications.
     *
     * @var array<int, array{column: string, direction: string}>
     */
    protected array $orders = [];

    /**
     * Row limit.
     */
    protected ?int $limit = null;

    /**
     * Row offset.
     */
    protected ?int $offset = null;

    /**
     * @param Connection $connection Active database connection.
     * @param Grammar    $grammar    SQL dialect compiler.
     */
    public function __construct(
        protected readonly Connection $connection,
        protected readonly Grammar    $grammar,
    ) {}

    /**
     * Set the table which the query is targeting.
     */
    public function from(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Alias for from().
     */
    public function table(string $table): static
    {
        return $this->from($table);
    }

    /**
     * Set the columns to be selected.
     *
     * @param string|string[] ...$columns
     */
    public function select(string|array ...$columns): static
    {
        $this->columns = [];

        foreach ($columns as $column) {
            if (is_array($column)) {
                $this->columns = array_merge($this->columns, $column);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    /**
     * Add a basic where clause.
     *
     * @param  string $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string $boolean
     * @return static
     */
    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        // Handle where('status', 'active') shorthand
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => (string) $operator,
            'boolean'  => $boolean,
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR where clause.
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where in" clause.
     *
     * @param  string                $column
     * @param  array<int|string, mixed> $values
     * @param  string                $boolean
     * @param  bool                  $not
     * @return static
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        if (empty($values)) {
            // WHERE 0 = 1 if empty values provided
            return $this->whereRaw('0 = 1', [], $boolean);
        }

        $this->wheres[] = [
            'type'    => 'in',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => $boolean,
            'not'     => $not,
        ];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add a "where not in" clause.
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, not: true);
    }

    /**
     * Add a "where null" clause.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type'    => 'null',
            'column'  => $column,
            'boolean' => $boolean,
            'not'     => $not,
        ];

        return $this;
    }

    /**
     * Add a "where not null" clause.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, not: true);
    }

    /**
     * Add a raw where clause with bindings.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     * @param  string            $boolean
     * @return static
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type'    => 'raw',
            'sql'     => $sql,
            'boolean' => $boolean,
        ];

        foreach ($bindings as $val) {
            $this->bindings[] = $val;
        }

        return $this;
    }

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): static
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    /**
     * Add a left join clause to the query.
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add an order-by clause.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column'    => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
        ];
        return $this;
    }

    /**
     * Add an order by created_at DESC (or custom column).
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an order by created_at ASC (or custom column).
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Set row limit.
     */
    public function limit(int $value): static
    {
        $this->limit = max(0, $value);
        return $this;
    }

    /**
     * Alias for limit().
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set row offset.
     */
    public function offset(int $value): static
    {
        $this->offset = max(0, $value);
        return $this;
    }

    /**
     * Alias for offset().
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    // =========================================================================
    //  Execution Methods
    // =========================================================================

    /**
     * Execute query and get all results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $sql = $this->grammar->compileSelect($this);
        return $this->connection->select($sql, $this->bindings);
    }

    /**
     * Execute query and get the first result, or null.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit(1);
        $sql = $this->grammar->compileSelect($clone);
        return $this->connection->selectOne($sql, $clone->bindings);
    }

    /**
     * Find a single record by primary key.
     *
     * @param  int|string $id
     * @param  string     $key
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, string $key = 'id'): ?array
    {
        return $this->where($key, $id)->first();
    }

    /**
     * Get a single column value from the first row.
     */
    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row !== null ? ($row[$column] ?? null) : null;
    }

    /**
     * Get record count matching the current query.
     */
    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) as aggregate'];
        $sql = $this->grammar->compileSelect($clone);
        $result = $this->connection->selectOne($sql, $clone->bindings);

        return (int) ($result['aggregate'] ?? 0);
    }

    /**
     * Check if any matching records exist.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Insert a new record and return last inserted ID.
     *
     * @param  array<string, mixed> $values
     * @return int|string
     */
    public function insert(array $values): int|string
    {
        $sql = $this->grammar->compileInsert($this, $values);
        return $this->connection->insert($sql, array_values($values));
    }

    /**
     * Update matching records with new values and return affected row count.
     *
     * @param  array<string, mixed> $values
     * @return int
     */
    public function update(array $values): int
    {
        $sql      = $this->grammar->compileUpdate($this, $values);
        $bindings = array_merge(array_values($values), $this->bindings);

        return $this->connection->update($sql, $bindings);
    }

    /**
     * Delete matching records and return affected row count.
     */
    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this);
        return $this->connection->delete($sql, $this->bindings);
    }

    /**
     * Paginate results.
     *
     * @param  int $perPage Number of items per page.
     * @param  int $page    Current page number (1-based).
     * @return array{data: array<int, array<string, mixed>>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $this->count();

        $offset = ($page - 1) * $perPage;
        $items  = $this->limit($perPage)->offset($offset)->get();

        return [
            'data'         => $items,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    // =========================================================================
    //  Accessors for Grammar
    // =========================================================================

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}
