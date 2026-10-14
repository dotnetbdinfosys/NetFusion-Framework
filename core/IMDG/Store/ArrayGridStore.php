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

namespace NetFusion\Core\IMDG\Store;

use NetFusion\Core\IMDG\Contracts\Store\GridPersistenceStoreInterface;

class ArrayGridStore implements GridPersistenceStoreInterface
{
    /** @var array<string, array<string, array{value: mixed, expiresAt: float}>> */
    protected array $data = [];

    public function load(string $mapName, string $key): mixed
    {
        if (!isset($this->data[$mapName][$key])) {
            return null;
        }

        $entry = $this->data[$mapName][$key];
        if ($entry['expiresAt'] > 0.0 && microtime(true) > $entry['expiresAt']) {
            unset($this->data[$mapName][$key]);
            return null;
        }

        return $entry['value'];
    }

    public function loadAll(string $mapName): array
    {
        if (!isset($this->data[$mapName])) {
            return [];
        }

        $now = microtime(true);
        $result = [];

        foreach ($this->data[$mapName] as $key => $entry) {
            if ($entry['expiresAt'] > 0.0 && $now > $entry['expiresAt']) {
                unset($this->data[$mapName][$key]);
                continue;
            }
            $result[$key] = $entry['value'];
        }

        return $result;
    }

    public function store(string $mapName, string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $expiresAt = $ttlSeconds > 0 ? (microtime(true) + $ttlSeconds) : 0.0;
        $this->data[$mapName][$key] = [
            'value' => $value,
            'expiresAt' => $expiresAt,
        ];
    }

    public function storeAll(string $mapName, array $entries): void
    {
        foreach ($entries as $key => $value) {
            $this->store($mapName, (string) $key, $value);
        }
    }

    public function delete(string $mapName, string $key): void
    {
        unset($this->data[$mapName][$key]);
    }

    public function deleteAll(string $mapName): void
    {
        unset($this->data[$mapName]);
    }
}
