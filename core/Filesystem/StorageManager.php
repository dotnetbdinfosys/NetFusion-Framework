<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/StorageManager.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem
| Purpose   : Central storage manager resolving and caching filesystem disks (Local, S3, Custom).
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

namespace NetFusion\Core\Filesystem;

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Filesystem\Contracts\FilesystemInterface;
use NetFusion\Core\Filesystem\Drivers\LocalDriver;
use NetFusion\Core\Filesystem\Drivers\S3Driver;

/**
 * Class StorageManager
 *
 * Manages multiple filesystem disks and provides fallback to the default configured disk.
 *
 * @package NetFusion\Core\Filesystem
 * @mixin \NetFusion\Core\Filesystem\Contracts\FilesystemInterface
 */
class StorageManager
{
    /**
     * The array of resolved filesystem disks.
     *
     * @var array<string, FilesystemInterface>
     */
    protected array $disks = [];

    /**
     * Custom driver creators.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    public function __construct(protected Application $app)
    {
    }

    /**
     * Get a filesystem disk instance by name.
     *
     * @param string|null $name
     * @return FilesystemInterface
     */
    public function disk(?string $name = null): FilesystemInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->resolve($name);
        }

        return $this->disks[$name];
    }

    /**
     * Resolve the given disk by configuration.
     *
     * @param string $name
     * @return FilesystemInterface
     * @throws InvalidArgumentException
     */
    protected function resolve(string $name): FilesystemInterface
    {
        $config = config("filesystems.disks.{$name}");

        if (empty($config) || !is_array($config)) {
            // Provide sensible in-memory / storage fallback for local and public if missing
            if ($name === 'local') {
                return new LocalDriver(storage_path('app'), '', 0755);
            }
            if ($name === 'public') {
                return new LocalDriver(storage_path('app/public'), '/storage', 0755);
            }
            throw new InvalidArgumentException(sprintf('Filesystem disk [%s] is not configured.', $name));
        }

        $driver = $config['driver'] ?? 'local';

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($this->app, $config);
        }

        return match ($driver) {
            'local' => $this->createLocalDriver($config),
            's3'    => $this->createS3Driver($config),
            default => throw new InvalidArgumentException(sprintf('Unsupported filesystem driver [%s].', $driver)),
        };
    }

    /**
     * Create an instance of the LocalDriver.
     *
     * @param array<string, mixed> $config
     * @return LocalDriver
     */
    protected function createLocalDriver(array $config): LocalDriver
    {
        $root = (string) ($config['root'] ?? storage_path('app'));
        $url  = (string) ($config['url'] ?? '');
        $perm = (int) ($config['permissions'] ?? 0755);

        return new LocalDriver($root, $url, $perm);
    }

    /**
     * Create an instance of the S3Driver.
     *
     * @param array<string, mixed> $config
     * @return S3Driver
     */
    protected function createS3Driver(array $config): S3Driver
    {
        return new S3Driver($config);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param Closure $callback
     * @return $this
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Get the default disk driver name.
     */
    public function getDefaultDriver(): string
    {
        return (string) (config('filesystems.default') ?: 'local');
    }

    /**
     * Set the default disk driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->app['config']->set('filesystems.default', $name);
    }

    /**
     * Dynamically call the default disk instance.
     *
     * @param string $method
     * @param array<mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->$method(...$parameters);
    }
}
