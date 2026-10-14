<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Stores/ArrayStore.php
| Module    : Core\Cache\Stores
| Namespace : NetFusion\Core\Cache\Stores
| Purpose   : In-memory array cache driver for testing and single request scopes.
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
 * NetFusion Array Cache Store
 *
 * Ephemeral memory-based cache store ideal for unit testing and request isolation.
 *
 * @package NetFusion\Core\Cache\Stores
 * @version 1.0.0
 */
class ArrayStore implements CacheStoreInterface
{
    /**
     * Cache storage array containing [value, expires_at].
     *
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    protected array $storage = [];

    /**
     * @param string $prefix
     */
    public function __construct(
        protected readonly string $prefix = '',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        $k = $this->prefix . $key;

        if (!isset($this->storage[$k])) {
            return null;
        }

        $item = $this->storage[$k];

        if ($item['expires_at'] !== null && time() >= $item['expires_at']) {
            unset($this->storage[$k]);
            return null;
        }

        return $item['value'];
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $k = $this->prefix . $key;
        $expiresAt = $ttl !== null ? time() + $ttl : null;

        $this->storage[$k] = [
            'value'      => $value,
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key);

        if ($current === null) {
            $this->forever($key, $value);
            return $value;
        }

        if (!is_numeric($current)) {
            return false;
        }

        $new = (int) $current + $value;
        $k = $this->prefix . $key;
        $this->storage[$k]['value'] = $new;

        return $new;
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
        $k = $this->prefix . $key;
        if (isset($this->storage[$k])) {
            unset($this->storage[$k]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        $this->storage = [];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
