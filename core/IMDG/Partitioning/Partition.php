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

namespace NetFusion\Core\IMDG\Partitioning;

class Partition
{
    protected int $id;
    protected ?string $primaryOwnerId = null;
    /** @var array<string> */
    protected array $backupOwnerIds = [];
    /** @var array<string, array<string, array{value: mixed, expiresAt: float}>> [mapName => [key => entry]] */
    protected array $data = [];

    public function __construct(int $id, ?string $primaryOwnerId = null)
    {
        $this->id = $id;
        $this->primaryOwnerId = $primaryOwnerId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPrimaryOwnerId(): ?string
    {
        return $this->primaryOwnerId;
    }

    public function setPrimaryOwnerId(?string $nodeId): self
    {
        $this->primaryOwnerId = $nodeId;
        return $this;
    }

    public function getBackupOwnerIds(): array
    {
        return $this->backupOwnerIds;
    }

    public function setBackupOwnerIds(array $backupIds): self
    {
        $this->backupOwnerIds = $backupIds;
        return $this;
    }

    public function get(string $mapName, string $key): mixed
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

    public function put(string $mapName, string $key, mixed $value, int $ttlSeconds = 0): mixed
    {
        $prev = $this->get($mapName, $key);
        $expiresAt = $ttlSeconds > 0 ? (microtime(true) + $ttlSeconds) : 0.0;

        $this->data[$mapName][$key] = [
            'value' => $value,
            'expiresAt' => $expiresAt,
        ];

        return $prev;
    }

    public function remove(string $mapName, string $key): mixed
    {
        $prev = $this->get($mapName, $key);
        unset($this->data[$mapName][$key]);
        return $prev;
    }

    public function containsKey(string $mapName, string $key): bool
    {
        return $this->get($mapName, $key) !== null;
    }

    public function keySet(string $mapName): array
    {
        $this->pruneExpired($mapName);
        return isset($this->data[$mapName]) ? array_keys($this->data[$mapName]) : [];
    }

    public function values(string $mapName): array
    {
        $this->pruneExpired($mapName);
        if (!isset($this->data[$mapName])) {
            return [];
        }

        $values = [];
        foreach ($this->data[$mapName] as $entry) {
            $values[] = $entry['value'];
        }
        return $values;
    }

    public function size(?string $mapName = null): int
    {
        if ($mapName !== null) {
            $this->pruneExpired($mapName);
            return isset($this->data[$mapName]) ? count($this->data[$mapName]) : 0;
        }

        $total = 0;
        foreach (array_keys($this->data) as $map) {
            $this->pruneExpired($map);
            $total += count($this->data[$map]);
        }
        return $total;
    }

    public function clear(?string $mapName = null): void
    {
        if ($mapName !== null) {
            unset($this->data[$mapName]);
        } else {
            $this->data = [];
        }
    }

    protected function pruneExpired(string $mapName): void
    {
        if (!isset($this->data[$mapName])) {
            return;
        }

        $now = microtime(true);
        foreach ($this->data[$mapName] as $k => $entry) {
            if ($entry['expiresAt'] > 0.0 && $now > $entry['expiresAt']) {
                unset($this->data[$mapName][$k]);
            }
        }
    }
}
