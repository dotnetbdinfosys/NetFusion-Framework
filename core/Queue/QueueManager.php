<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/QueueManager.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue
| Purpose   : Central manager and driver factory for background queue operations.
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

namespace NetFusion\Core\Queue;

use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Queue\Contracts\JobInterface;
use NetFusion\Core\Queue\Contracts\QueueDriverInterface;
use NetFusion\Core\Queue\Drivers\DatabaseDriver;
use NetFusion\Core\Queue\Drivers\SyncDriver;
use InvalidArgumentException;

/**
 * NetFusion Queue Manager
 *
 * Resolves queue drivers, manages connection configurations, and proxies
 * dispatch calls to the default driver.
 *
 * @package NetFusion\Core\Queue
 * @version 1.0.0
 */
class QueueManager implements QueueDriverInterface
{
    /**
     * Active driver instances.
     *
     * @var array<string, QueueDriverInterface>
     */
    protected array $drivers = [];

    /**
     * Default connection name.
     */
    protected string $defaultConnection;

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app,
    ) {
        $this->defaultConnection = (string) config('queue.default', env('QUEUE_CONNECTION', 'sync'));
    }

    /**
     * Get a queue driver instance by connection name.
     *
     * @param  string|null $name
     * @return QueueDriverInterface
     */
    public function connection(?string $name = null): QueueDriverInterface
    {
        $name = ($name === null || $name === 'default') ? $this->defaultConnection : $name;

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolveDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Resolve a driver instance based on config.
     */
    protected function resolveDriver(string $name): QueueDriverInterface
    {
        $config = config("queue.connections.{$name}", [
            'driver' => $name,
        ]);

        $driverType = $config['driver'] ?? $name;

        return match ($driverType) {
            'sync' => new SyncDriver(),
            'database' => new DatabaseDriver(
                connection: $this->app->make(DatabaseManager::class)->connection($config['connection'] ?? null),
                table: $config['table'] ?? 'netfusion_jobs',
            ),
            default => throw new InvalidArgumentException("Unsupported queue driver: [{$driverType}]."),
        };
    }

    /**
     * Set default connection.
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Get default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * {@inheritDoc}
     */
    public function push(JobInterface|string $job, string $queue = 'default'): string
    {
        return $this->connection()->push($job, $queue);
    }

    /**
     * {@inheritDoc}
     */
    public function later(int $delay, JobInterface|string $job, string $queue = 'default'): string
    {
        return $this->connection()->later($delay, $job, $queue);
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue = 'default'): ?JobInterface
    {
        return $this->connection()->pop($queue);
    }

    /**
     * {@inheritDoc}
     */
    public function acknowledge(JobInterface $job): void
    {
        $this->connection()->acknowledge($job);
    }

    /**
     * {@inheritDoc}
     */
    public function release(JobInterface $job, int $delay = 0): void
    {
        $this->connection()->release($job, $delay);
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue = 'default'): int
    {
        return $this->connection()->size($queue);
    }

    /**
     * {@inheritDoc}
     */
    public function purge(string $queue = 'default'): int
    {
        return $this->connection()->purge($queue);
    }
}
