<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Config/Config.php
| Module    : Core\Config
| Namespace : NetFusion\Core\Config
| Purpose   : Lazy-loading, dot-notation configuration repository with runtime merging and caching.
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
| File      : core/Config/Config.php
| Module    : Core\Config
| Namespace : NetFusion\Core\Config
| Purpose   : Lazy-loading, dot-notation configuration repository with runtime merging and caching.
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
| File      : core/Config/Config.php
| Module    : Core\Config
| Namespace : NetFusion\Core\Config
| Purpose   : Lazy-loading, dot-notation configuration repository with runtime merging and caching.
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

namespace NetFusion\Core\Config;

/**
 * NetFusion Config Manager
 *
 * A centralized, dot-notation config loader and accessor. Reads
 * PHP array-based config files from the `config/` directory,
 * merges them with an in-memory registry, and supports deep
 * dot-notation access such as:
 *   config('database.connections.mysql.host')
 *
 * Config files are loaded lazily (on first access) and then
 * cached in memory for near-zero overhead on subsequent calls.
 *
 * @package NetFusion\Core\Config
 * @version 1.0.0
 */
final class Config
{
    /**
     * The in-memory configuration store.
     * Keys are file names (e.g., 'database') mapped to their arrays.
     *
     * @var array<string, mixed>
     */
    private array $items = [];

    /**
     * The path to the config directory.
     */
    private readonly string $configPath;

    /**
     * @param string $configPath Absolute path to the config/ directory.
     */
    public function __construct(string $configPath)
    {
        $this->configPath = rtrim($configPath, DIRECTORY_SEPARATOR);
    }

    /**
     * Retrieve a configuration value using dot notation.
     *
     * @template T
     *
     * @param  string  $key     Dot-notation config key (e.g., 'app.name').
     * @param  mixed   $default Default value if the key does not exist.
     * @return mixed            The configuration value or $default.
     *
     * @example
     *   $debug  = $config->get('app.debug');           // true
     *   $host   = $config->get('database.connections.mysql.host', '127.0.0.1');
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Short-circuit if the key is already fully loaded
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        // Extract the config file prefix (first segment before '.')
        $segments  = explode('.', $key);
        $fileAlias = array_shift($segments);

        // Lazy-load the config file if not yet in memory
        $this->loadFile($fileAlias);

        // If no deeper path, return the entire file config
        if (empty($segments)) {
            return $this->items[$fileAlias] ?? $default;
        }

        // Traverse the nested array with the remaining segments
        $data = $this->items[$fileAlias] ?? null;

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Determine if a configuration key exists.
     *
     * @param  string $key Dot-notation config key.
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key, null) !== null;
    }

    /**
     * Set a configuration value at runtime (in-memory only).
     * This does NOT persist to any config file.
     *
     * @param string $key   Dot-notation config key.
     * @param mixed  $value The value to set.
     */
    public function set(string $key, mixed $value): void
    {
        $segments  = explode('.', $key);
        $fileAlias = array_shift($segments);

        // Ensure the config file is loaded if not already in memory
        if (!isset($this->items[$fileAlias])) {
            $this->loadFile($fileAlias);
            if (!isset($this->items[$fileAlias])) {
                $this->items[$fileAlias] = [];
            }
        }

        // If we're setting a top-level key, assign directly
        if (empty($segments)) {
            $this->items[$fileAlias] = $value;
            return;
        }

        // Traverse and set deep keys
        $target = &$this->items[$fileAlias];
        foreach ($segments as $i => $segment) {
            $isLast = ($i === count($segments) - 1);

            if ($isLast) {
                $target[$segment] = $value;
            } else {
                if (!isset($target[$segment]) || !is_array($target[$segment])) {
                    $target[$segment] = [];
                }
                $target = &$target[$segment];
            }
        }
    }

    /**
     * Merge an array of config values into a top-level config namespace.
     * Useful for modules to register their own config at boot time.
     *
     * @param string              $alias  The config file alias / namespace key.
     * @param array<string,mixed> $config The configuration array to merge.
     */
    public function merge(string $alias, array $config): void
    {
        if (!isset($this->items[$alias])) {
            $this->loadFile($alias);
        }

        $this->items[$alias] = array_merge(
            $this->items[$alias] ?? [],
            $config
        );
    }

    /**
     * Get all configuration values for a given top-level file alias.
     *
     * @param  string              $alias The file alias (e.g., 'app', 'database').
     * @return array<string, mixed>
     */
    public function all(string $alias): array
    {
        $this->loadFile($alias);
        return $this->items[$alias] ?? [];
    }

    /**
     * Flush all loaded config from the in-memory cache.
     * Useful in tests to force fresh reads.
     */
    public function flush(): void
    {
        $this->items = [];
    }

    /**
     * Lazily load a config file from disk into the in-memory store.
     * If the file does not exist, the alias is initialized as an empty array.
     *
     * Config files must return a PHP array.
     *
     * @param string $alias The file alias (maps to config/{$alias}.php).
     */
    private function loadFile(string $alias): void
    {
        // Already loaded — skip disk I/O
        if (array_key_exists($alias, $this->items)) {
            return;
        }

        $filePath = $this->configPath . DIRECTORY_SEPARATOR . $alias . '.php';

        if (is_file($filePath) && is_readable($filePath)) {
            $data = require $filePath;

            if (!is_array($data)) {
                throw new \UnexpectedValueException(
                    sprintf(
                        'Config file [%s] must return a PHP array, got [%s].',
                        $filePath,
                        get_debug_type($data)
                    )
                );
            }

            $this->items[$alias] = $data;
        } else {
            // Non-existent config files are silently treated as empty
            $this->items[$alias] = [];
        }
    }
}
