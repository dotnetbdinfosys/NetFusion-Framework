<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Connection.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : PDO wrapper with prepared statements, atomic transaction helper, and query telemetry.
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
| File      : core/Database/Connection.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : PDO wrapper with prepared statements, atomic transaction helper, and query telemetry.
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
| File      : core/Database/Connection.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : PDO wrapper with prepared statements, atomic transaction helper, and query telemetry.
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

namespace NetFusion\Core\Database;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;

/**
 * NetFusion Database Connection
 *
 * A robust PDO wrapper offering prepared statement execution, transaction
 * management, parameter binding, last-insert ID retrieval, and query telemetry.
 *
 * @package NetFusion\Core\Database
 * @version 1.0.0
 */
class Connection
{
    /**
     * Active PDO instance.
     */
    protected ?PDO $pdo = null;

    /**
     * Log of executed queries (active in debug mode).
     *
     * @var array<int, array{query: string, bindings: array<int|string, mixed>, time_ms: float}>
     */
    protected array $queryLog = [];

    /**
     * Whether query logging is enabled.
     */
    protected bool $loggingQueries = false;

    /**
     * @param array<string, mixed> $config Connection configuration array.
     */
    public function __construct(
        protected readonly array $config
    ) {
        $this->loggingQueries = (bool) ($config['query_log'] ?? false);
    }

    /**
     * Get or lazily establish the underlying PDO connection.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createPdoConnection($this->config);
        }

        return $this->pdo;
    }

    /**
     * Execute a SELECT query and return all matching rows.
     *
     * @param  string                      $query
     * @param  array<int|string, mixed>    $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $query, array $bindings = []): array
    {
        $statement = $this->run($query, $bindings);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a SELECT query and return only the first row, or null.
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $rows = $this->select($query, $bindings);
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Execute an INSERT statement and return the last inserted ID.
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return int|string
     */
    public function insert(string $query, array $bindings = []): int|string
    {
        $this->run($query, $bindings);
        $id = $this->getPdo()->lastInsertId();
        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Execute an UPDATE statement and return the number of affected rows.
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return int
     */
    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute a DELETE statement and return the number of affected rows.
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return int
     */
    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an arbitrary modifying statement and return affected rows.
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return int
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        $statement = $this->run($query, $bindings);
        return $statement->rowCount();
    }

    /**
     * Execute a raw statement (e.g. DDL / schema queries).
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return bool
     */
    public function statement(string $query, array $bindings = []): bool
    {
        $statement = $this->run($query, $bindings);
        return $statement !== false;
    }

    /**
     * Prepare, bind, and execute a PDOStatement with timing telemetry.
     *
     * @param  string                   $query
     * @param  array<int|string, mixed> $bindings
     * @return PDOStatement
     *
     * @throws RuntimeException
     */
    protected function run(string $query, array $bindings = []): PDOStatement
    {
        $start = microtime(true);
        $pdo   = $this->getPdo();

        try {
            $statement = $pdo->prepare($query);

            foreach ($bindings as $key => $value) {
                $paramType = match (true) {
                    is_int($value)  => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                };

                // Handle 0-indexed positional parameters (? marks) vs named (:param)
                $paramIndex = is_int($key) ? $key + 1 : $key;
                $statement->bindValue($paramIndex, $value, $paramType);
            }

            $statement->execute();

        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Database Query Error: %s in query [%s]', $e->getMessage(), $query),
                (int) $e->getCode(),
                $e
            );
        }

        if ($this->loggingQueries) {
            $timeMs = round((microtime(true) - $start) * 1000, 3);
            $this->queryLog[] = [
                'query'    => $query,
                'bindings' => $bindings,
                'time_ms'  => $timeMs,
            ];
        }

        return $statement;
    }

    // =========================================================================
    //  Transactions
    // =========================================================================

    /**
     * Start a database transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public function rollBack(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Check if currently within a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * Execute a callback inside an atomic transaction.
     * Automatically commits on success, or rolls back on Throwable.
     *
     * @template T
     * @param  callable(Connection): T $callback
     * @return T
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    //  Query Telemetry & Logging
    // =========================================================================

    /**
     * Get all logged queries.
     *
     * @return array<int, array{query: string, bindings: array<int|string, mixed>, time_ms: float}>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     */
    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Get driver name for this connection.
     */
    public function getDriverName(): string
    {
        return strtolower((string) ($this->config['driver'] ?? 'mysql'));
    }

    // =========================================================================
    //  Connection Factory
    // =========================================================================

    /**
     * Establish the raw PDO instance based on driver configuration.
     *
     * @param  array<string, mixed> $config
     * @return PDO
     */
    protected function createPdoConnection(array $config): PDO
    {
        $driver = strtolower((string) ($config['driver'] ?? 'mysql'));

        $dsn = match ($driver) {
            'sqlite' => sprintf('sqlite:%s', $config['database']),
            'pgsql'  => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;options=\'--client_encoding=%s\'',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 5432,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8'
            ),
            'mysql'  => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 3306,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            ),
            default  => throw new RuntimeException(sprintf('Unsupported database driver [%s].', $driver)),
        };

        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options  = $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, $username, $password, $options);
    }
}
