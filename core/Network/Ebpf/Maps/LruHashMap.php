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

/**
 * eBPF LRU Hash Map (BPF_MAP_TYPE_LRU_HASH).
 * Automatically evicts Least-Recently-Used elements when capacity is reached.
 */
class LruHashMap implements MapInterface
{
    private string $name;
    private int $maxEntries;
    private array $data = [];

    public function __construct(string $name, int $maxEntries = 1000)
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
        return MapType::LRU_HASH;
    }

    public function getMaxEntries(): int
    {
        return $this->maxEntries;
    }

    public function get(int|string $key): mixed
    {
        $k = (string)$key;
        if (!isset($this->data[$k])) {
            return null;
        }

        // Move to end (most recently used)
        $val = $this->data[$k];
        unset($this->data[$k]);
        $this->data[$k] = $val;
        return $val;
    }

    public function set(int|string $key, mixed $value): void
    {
        $k = (string)$key;
        if (isset($this->data[$k])) {
            unset($this->data[$k]);
        } elseif (count($this->data) >= $this->maxEntries) {
            // Evict least recently used (first element)
            array_shift($this->data);
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
