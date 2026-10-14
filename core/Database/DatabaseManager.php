<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/DatabaseManager.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : Multi-connection manager supporting named connections and dynamic tenant database switching.
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
| File      : core/Database/DatabaseManager.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : Multi-connection manager supporting named connections and dynamic tenant database switching.
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
| File      : core/Database/DatabaseManager.php
| Module    : Core\Database
| Namespace : NetFusion\Core\Database
| Purpose   : Multi-connection manager supporting named connections and dynamic tenant database switching.
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

use NetFusion\Core\Application;
use NetFusion\Core\Database\Query\Builder;
use NetFusion\Core\Database\Query\Grammar;
use RuntimeException;

/**
 * NetFusion Database Manager
 *
 * Manages multiple database connections, query builder instantiations,
 * and dynamic per-tenant database switching.
 *
 * @package NetFusion\Core\Database
 * @version 1.0.0
 */
class DatabaseManager
{
    /**
     * Active connection instances cached by name.
     *
     * @var array<string, Connection>
     */
    protected array $connections = [];

    /**
     * Current tenant connection identifier (if any).
     */
    protected ?string $activeTenantId = null;

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app
    ) {}

    /**
     * Get a database connection by name (or default connection).
     *
     * @param  string|null $name Connection name ('mysql', 'pgsql', 'sqlite', 'tenant').
     * @return Connection
     */
    public function connection(?string $name = null): Connection
    {
        $name = $name ?? $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Get a new Query Builder instance for the given table.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return Builder
     */
    public function table(string $table, ?string $connection = null): Builder
    {
        $conn    = $this->connection($connection);
        $grammar = new Grammar($conn->getDriverName());

        return (new Builder($conn, $grammar))->from($table);
    }

    /**
     * Set dynamic tenant connection.
     * Creates or switches a dedicated 'tenant' named connection on the fly.
     *
     * @param  string               $tenantId
     * @param  array<string, mixed> $customConfig
     * @return Connection
     */
    public function setTenantConnection(string $tenantId, array $customConfig = []): Connection
    {
        $this->activeTenantId = $tenantId;

        // Disconnect previous tenant connection if present
        $this->disconnect('tenant');

        $template = config('database.tenant_connection_template', []);
        $prefix   = config('database.tenant_db_prefix', 'tenant_');

        $dbName = $customConfig['database'] ?? ($prefix . $tenantId);

        $tenantConfig = array_merge($template, $customConfig, [
            'database' => $dbName,
        ]);

        $connection = new Connection($tenantConfig);
        $this->connections['tenant'] = $connection;

        return $connection;
    }

    /**
     * Get the active tenant ID (or null if single-tenant mode).
     */
    public function getActiveTenantId(): ?string
    {
        return $this->activeTenantId;
    }

    /**
     * Disconnect and clear a cached connection.
     */
    public function disconnect(?string $name = null): void
    {
        $name = $name ?? $this->getDefaultConnection();
        unset($this->connections[$name]);
    }

    /**
     * Reconnect to the given database.
     */
    public function reconnect(?string $name = null): Connection
    {
        $this->disconnect($name);
        return $this->connection($name);
    }

    /**
     * Get default database connection name.
     */
    public function getDefaultConnection(): string
    {
        return (string) config('database.default', 'mysql');
    }

    /**
     * Set default connection name.
     */
    public function setDefaultConnection(string $name): void
    {
        config(['database.default' => $name]);
    }

    /**
     * Create a new Connection instance by name.
     *
     * @param  string $name
     * @return Connection
     *
     * @throws RuntimeException
     */
    protected function makeConnection(string $name): Connection
    {
        $config = config("database.connections.{$name}");

        if (!is_array($config) || empty($config)) {
            throw new RuntimeException(sprintf('Database connection [%s] is not configured in config/database.php.', $name));
        }

        return new Connection($config);
    }

    /**
     * Dynamically pass methods to the default connection.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}
