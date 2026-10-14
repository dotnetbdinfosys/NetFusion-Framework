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

namespace NetFusion\Core\Network\Ebpf\Maps;

use NetFusion\Core\Network\Ebpf\Enums\MapType;
use NetFusion\Core\Network\Ebpf\Exceptions\MapException;

/**
 * eBPF Hash Table Map (BPF_MAP_TYPE_HASH).
 * Key-value mapping table for fast IP/port lookup.
 */
class HashMap implements MapInterface
{
    private string $name;
    private int $maxEntries;
    private array $data = [];

    public function __construct(string $name, int $maxEntries = 10000)
    {
        $this->name       = $name;
        $this->maxEntries = $maxEntries;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): MapType
    {
        return MapType::HASH;
    }

    public function getMaxEntries(): int
    {
        return $this->maxEntries;
    }

    public function get(int|string $key): mixed
    {
        return $this->data[(string)$key] ?? null;
    }

    public function set(int|string $key, mixed $value): void
    {
        $k = (string)$key;
        if (!isset($this->data[$k]) && count($this->data) >= $this->maxEntries) {
            throw new MapException("Hash map table is full (max entries: {$this->maxEntries})");
        }
        $this->data[$k] = $value;
    }

    public function delete(int|string $key): bool
    {
        $k = (string)$key;
        if (isset($this->data[$k])) {
            unset($this->data[$k]);
            return true;
        }
        return false;
    }

    public function has(int|string $key): bool
    {
        return isset($this->data[(string)$key]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function all(): array
    {
        return $this->data;
    }
}
