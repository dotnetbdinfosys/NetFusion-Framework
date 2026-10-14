<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Stores/FileStore.php
| Module    : Core\Cache\Stores
| Namespace : NetFusion\Core\Cache\Stores
| Purpose   : High-performance atomic file-based cache driver with subfolder sharding.
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

namespace NetFusion\Core\Cache\Stores;

use NetFusion\Core\Cache\Contracts\CacheStoreInterface;

/**
 * NetFusion File Cache Store
 *
 * Persists cached items to local disk with sub-directory hashing and atomic writes.
 *
 * @package NetFusion\Core\Cache\Stores
 * @version 1.0.0
 */
class FileStore implements CacheStoreInterface
{
    /**
     * @param string $directory Base cache directory.
     * @param string $prefix    Cache key prefix.
     */
    public function __construct(
        protected readonly string $directory,
        protected readonly string $prefix = '',
    ) {
        $this->ensureDirectoryExists($this->directory);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false || strlen($content) < 10) {
            return null;
        }

        $expire = (int) substr($content, 0, 10);

        if ($expire !== 0 && time() >= $expire) {
            $this->forget($key);
            return null;
        }

        $serialized = substr($content, 10);

        return @unserialize($serialized);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $path = $this->path($key);
        $this->ensureDirectoryExists(dirname($path));

        // 10-digit expire timestamp (0000000000 = forever)
        $expire = $ttl !== null ? time() + $ttl : 0;
        $payload = sprintf('%010d', $expire) . serialize($value);

        // Atomic write via tempnam + rename
        $tempPath = tempnam(dirname($path), 'nf_cache_');
        if ($tempPath === false) {
            return false;
        }

        if (file_put_contents($tempPath, $payload) === false) {
            @unlink($tempPath);
            return false;
        }

        return @rename($tempPath, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $path = $this->path($key);
        $this->ensureDirectoryExists(dirname($path));

        $fp = @fopen($path, 'c+');
        if (!$fp) {
            return false;
        }

        flock($fp, LOCK_EX);

        $content = stream_get_contents($fp);
        $expire = 0;
        $current = 0;

        if (!empty($content) && strlen($content) >= 10) {
            $expire = (int) substr($content, 0, 10);
            if ($expire === 0 || time() < $expire) {
                $unserialized = @unserialize(substr($content, 10));
                if (is_numeric($unserialized)) {
                    $current = (int) $unserialized;
                }
            }
        }

        $newVal = $current + $value;
        $payload = sprintf('%010d', $expire) . serialize($newVal);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $payload);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $newVal;
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * {@inheritDoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, null);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): bool
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        return $this->cleanDirectory($this->directory);
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the full file path for a cache key.
     */
    public function path(string $key): string
    {
        $hash = sha1($this->prefix . $key);
        $parts = array_slice(str_split($hash, 2), 0, 2);

        return $this->directory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR . $hash;
    }

    /**
     * Recursively purge directory files.
     */
    protected function cleanDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        return true;
    }

    /**
     * Ensure storage directory exists and is writable.
     */
    protected function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
