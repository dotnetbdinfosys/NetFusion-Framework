<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/SearchManager.php
| Module    : Search
| Namespace : NetFusion\Core\Search
| Purpose   : Central multi-driver search orchestrator & manager.
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

namespace NetFusion\Core\Search;

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Container\Container;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Search\Contracts\SearchDriverInterface;
use NetFusion\Core\Search\Drivers\MemoryDriver;
use NetFusion\Core\Search\Drivers\MysqlFulltextDriver;
use NetFusion\Core\Search\Drivers\SqliteFtsDriver;
use PDO;

/**
 * Class SearchManager
 *
 * Central enterprise search manager managing drivers (Memory, SQLite FTS, MySQL FULLTEXT)
 * and providing unified search and indexing operations.
 *
 * @package NetFusion\Core\Search
 */
class SearchManager
{
    /**
     * Active resolved driver instances.
     *
     * @var array<string, SearchDriverInterface>
     */
    protected array $drivers = [];

    /**
     * Custom driver creator callbacks.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * Default driver name.
     */
    protected ?string $defaultDriver = null;

    /**
     * @param Container|Application|null      $app
     * @param SearchDriverInterface|null      $defaultDriverInstance
     */
    public function __construct(
        protected Container|Application|null $app = null,
        ?SearchDriverInterface $defaultDriverInstance = null
    ) {
        if ($defaultDriverInstance !== null) {
            $this->drivers['default'] = $defaultDriverInstance;
            $this->defaultDriver = 'default';
        }
    }

    /**
     * Get a search driver instance by name.
     *
     * @param string|null $driver
     * @return SearchDriverInterface
     */
    public function driver(?string $driver = null): SearchDriverInterface
    {
        $driver = $driver ?? $this->getDefaultDriver();

        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string  $driver
     * @param Closure $callback
     * @return $this
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        if ($this->defaultDriver !== null) {
            return $this->defaultDriver;
        }

        if ($this->app !== null && $this->app->has('config') && function_exists('config')) {
            try {
                $configured = config('search.default');
                if (is_string($configured) && $configured !== '') {
                    return $this->defaultDriver = $configured;
                }
            } catch (\Throwable) {
                // Fall through to default
            }
        }

        return $this->defaultDriver = 'memory';
    }

    /**
     * Set the default driver name.
     *
     * @param string $name
     * @return $this
     */
    public function setDefaultDriver(string $name): static
    {
        $this->defaultDriver = $name;
        return $this;
    }

    /**
     * Perform full-text search and return a SearchResult value object.
     *
     * @param string               $index   Target index name
     * @param string               $query   Search query
     * @param array<string, mixed> $options
     * @return SearchResult
     */
    public function search(string $index, string $query, array $options = []): SearchResult
    {
        $page = isset($options['page']) ? (int) $options['page'] : 1;
        $perPage = isset($options['limit']) ? (int) $options['limit'] : 15;

        // Auto calculate offset if page is provided
        if (! isset($options['offset']) && $page > 1) {
            $options['offset'] = ($page - 1) * $perPage;
        }

        $raw = $this->driver()->search($index, $query, $options);

        return new SearchResult(
            hits: $raw['hits'] ?? [],
            total: $raw['total'] ?? 0,
            took: $raw['took'] ?? 0.0,
            query: $query,
            page: $page,
            perPage: $perPage
        );
    }

    /**
     * Get raw array search results directly from driver.
     *
     * @param string               $index
     * @param string               $query
     * @param array<string, mixed> $options
     * @return array
     */
    public function raw(string $index, string $query, array $options = []): array
    {
        return $this->driver()->search($index, $query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function index(string $index, array $documents): void
    {
        $this->driver()->index($index, $documents);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $index, array|int|string $ids): void
    {
        $this->driver()->delete($index, $ids);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(string $index): void
    {
        $this->driver()->flush($index);
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex(string $index, array $fields = []): void
    {
        $this->driver()->createIndex($index, $fields);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $index): void
    {
        $this->driver()->dropIndex($index);
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(string $index): bool
    {
        return $this->driver()->hasIndex($index);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $index): int
    {
        return $this->driver()->count($index);
    }

    /**
     * Create a driver instance by name.
     *
     * @param string $driver
     * @return SearchDriverInterface
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver): SearchDriverInterface
    {
        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($this->app);
        }

        $method = 'create' . ucfirst($driver) . 'Driver';
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Unsupported search driver [{$driver}].");
    }

    /**
     * Create in-memory driver instance.
     *
     * @return MemoryDriver
     */
    protected function createMemoryDriver(): MemoryDriver
    {
        return new MemoryDriver();
    }

    /**
     * Create SQLite FTS driver instance.
     *
     * @return SqliteFtsDriver
     */
    protected function createSqliteDriver(): SqliteFtsDriver
    {
        $pdo = null;

        if ($this->app !== null && $this->app->has(DatabaseManager::class)) {
            /** @var DatabaseManager $db */
            $db = $this->app->make(DatabaseManager::class);
            try {
                $conn = $db->connection('sqlite');
                $pdo = $conn->getPdo();
            } catch (\Throwable) {
                // Ignore if sqlite connection not configured
            }
        }

        if ($pdo === null) {
            $path = ($this->app !== null && method_exists($this->app, 'basePath'))
                ? $this->app->basePath('storage/search/index.sqlite')
                : ':memory:';
            return new SqliteFtsDriver($path);
        }

        return new SqliteFtsDriver($pdo);
    }

    /**
     * Create MySQL Fulltext driver instance.
     *
     * @return MysqlFulltextDriver
     */
    protected function createMysqlDriver(): MysqlFulltextDriver
    {
        $pdo = null;

        if ($this->app !== null && $this->app->has(DatabaseManager::class)) {
            /** @var DatabaseManager $db */
            $db = $this->app->make(DatabaseManager::class);
            try {
                $conn = $db->connection('mysql');
                $pdo = $conn->getPdo();
            } catch (\Throwable) {
                // Fallback to default connection
                try {
                    $pdo = $db->connection()->getPdo();
                } catch (\Throwable) {
                    // Ignore
                }
            }
        }

        if ($pdo === null) {
            throw new InvalidArgumentException(
                "Cannot initialize MySQL search driver: No active database connection available."
            );
        }

        return new MysqlFulltextDriver($pdo);
    }

    /**
     * Dynamically pass missing calls to default driver.
     *
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
