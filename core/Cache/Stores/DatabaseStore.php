<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Stores/DatabaseStore.php
| Module    : Core\Cache\Stores
| Namespace : NetFusion\Core\Cache\Stores
| Purpose   : Relational database cache store supporting MySQL, PostgreSQL, and SQLite.
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
use NetFusion\Core\Database\Connection;

/**
 * NetFusion Database Cache Store
 *
 * Persists cached items into a relational database table with automatic expiration.
 *
 * @package NetFusion\Core\Cache\Stores
 * @version 1.0.0
 */
class DatabaseStore implements CacheStoreInterface
{
    /**
     * @param Connection $connection
     * @param string     $table
     * @param string     $prefix
     */
    public function __construct(
        protected readonly Connection $connection,
        protected readonly string     $table = 'netfusion_cache',
        protected readonly string     $prefix = '',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        $prefixedKey = $this->prefix . $key;
        $kCol = $this->quote('key');
        $vCol = $this->quote('value');
        $eCol = $this->quote('expiration');

        $row = $this->connection->selectOne(
            "SELECT {$vCol}, {$eCol} FROM {$this->table} WHERE {$kCol} = ?",
            [$prefixedKey]
        );

        if (!$row) {
            return null;
        }

        $expiration = (int) $row['expiration'];

        if ($expiration > 0 && time() >= $expiration) {
            $this->forget($key);
            return null;
        }

        return @unserialize((string) $row['value']);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $prefixedKey = $this->prefix . $key;
        $expiration  = $ttl !== null ? time() + $ttl : 0;
        $serialized  = serialize($value);

        $kCol = $this->quote('key');
        $vCol = $this->quote('value');
        $eCol = $this->quote('expiration');

        $existing = $this->connection->selectOne(
            "SELECT {$kCol} FROM {$this->table} WHERE {$kCol} = ?",
            [$prefixedKey]
        );

        if ($existing) {
            $this->connection->update(
                "UPDATE {$this->table} SET {$vCol} = ?, {$eCol} = ? WHERE {$kCol} = ?",
                [$serialized, $expiration, $prefixedKey]
            );
        } else {
            $this->connection->insert(
                "INSERT INTO {$this->table} ({$kCol}, {$vCol}, {$eCol}) VALUES (?, ?, ?)",
                [$prefixedKey, $serialized, $expiration]
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->connection->transaction(function () use ($key, $value) {
            $current = $this->get($key);

            if ($current === null) {
                $this->forever($key, $value);
                return $value;
            }

            if (!is_numeric($current)) {
                return false;
            }

            $newVal = (int) $current + $value;
            $this->forever($key, $newVal);

            return $newVal;
        });
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
        $prefixedKey = $this->prefix . $key;
        $kCol = $this->quote('key');

        $affected = $this->connection->delete(
            "DELETE FROM {$this->table} WHERE {$kCol} = ?",
            [$prefixedKey]
        );

        return $affected > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        if ($this->prefix === '') {
            $this->connection->delete("DELETE FROM {$this->table}");
            return true;
        }

        $kCol = $this->quote('key');
        $this->connection->delete(
            "DELETE FROM {$this->table} WHERE {$kCol} LIKE ?",
            [$this->prefix . '%']
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Quote column name based on database dialect.
     */
    protected function quote(string $column): string
    {
        return match ($this->connection->getDriverName()) {
            'mysql' => "`{$column}`",
            'pgsql' => "\"{$column}\"",
            default => "`{$column}`",
        };
    }
}
