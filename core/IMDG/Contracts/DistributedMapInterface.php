<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\IMDG\Contracts;

interface DistributedMapInterface
{
    /**
     * Retrieve an item from the map.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the map.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttlSeconds (0 for infinite)
     * @return mixed Previous value if any
     */
    public function put(string $key, mixed $value, int $ttlSeconds = 0): mixed;

    /**
     * Put an item only if key does not already exist.
     */
    public function putIfAbsent(string $key, mixed $value, int $ttlSeconds = 0): bool;

    /**
     * Remove an item from the map.
     */
    public function remove(string $key): mixed;

    /**
     * Check if a key exists in the map.
     */
    public function containsKey(string $key): bool;

    /**
     * Check if a value exists in the map.
     */
    public function containsValue(mixed $value): bool;

    /**
     * Execute an in-place atomic EntryProcessor on a key.
     */
    public function executeOnKey(string $key, EntryProcessorInterface $processor): mixed;

    /**
     * Execute an EntryProcessor across all keys in the map.
     *
     * @return array<string, mixed>
     */
    public function executeOnEntries(EntryProcessorInterface $processor): array;

    /**
     * Register a continuous query listener for real-time mutations.
     *
     * @param ContinuousQueryListenerInterface|callable $listener
     * @param callable|null $filter
     * @return string Listener registration ID
     */
    public function addEntryListener(ContinuousQueryListenerInterface|callable $listener, ?callable $filter = null): string;

    /**
     * Remove a continuous query listener.
     */
    public function removeEntryListener(string $listenerId): bool;

    /**
     * Get all keys in the map.
     *
     * @return array<string>
     */
    public function keySet(): array;

    /**
     * Get all values in the map.
     *
     * @return array<mixed>
     */
    public function values(): array;

    /**
     * Get number of entries.
     */
    public function size(): int;

    /**
     * Clear all entries from the map.
     */
    public function clear(): void;

    /**
     * Get map performance metrics (hits, misses, near-cache stats).
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array;
}
