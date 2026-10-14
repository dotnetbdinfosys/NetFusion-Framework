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

namespace NetFusion\Core\IMDG\Contracts\Store;

interface GridPersistenceStoreInterface
{
    /**
     * Load an entry value by map and key.
     */
    public function load(string $mapName, string $key): mixed;

    /**
     * Load all entries for a map.
     *
     * @return array<string, mixed>
     */
    public function loadAll(string $mapName): array;

    /**
     * Save an entry to persistence store.
     */
    public function store(string $mapName, string $key, mixed $value, int $ttlSeconds = 0): void;

    /**
     * Batch save multiple entries.
     *
     * @param string $mapName
     * @param array<string, mixed> $entries
     */
    public function storeAll(string $mapName, array $entries): void;

    /**
     * Delete an entry from persistence store.
     */
    public function delete(string $mapName, string $key): void;

    /**
     * Delete all entries for a map.
     */
    public function deleteAll(string $mapName): void;
}
