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
 * eBPF Array Map (BPF_MAP_TYPE_ARRAY).
 * Fixed-size indexed array memory structure.
 */
class ArrayMap implements MapInterface
{
    private string $name;
    private int $maxEntries;
    private array $data;

    public function __construct(string $name, int $maxEntries = 1024, int $defaultValue = 0)
    {
        $this->name       = $name;
        $this->maxEntries = $maxEntries;
        $this->data       = array_fill(0, $maxEntries, $defaultValue);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): MapType
    {
        return MapType::ARRAY;
    }

    public function getMaxEntries(): int
    {
        return $this->maxEntries;
    }

    public function get(int|string $key): mixed
    {
        $idx = (int)$key;
        if ($idx < 0 || $idx >= $this->maxEntries) {
            return null;
        }
        return $this->data[$idx];
    }

    public function set(int|string $key, mixed $value): void
    {
        $idx = (int)$key;
        if ($idx < 0 || $idx >= $this->maxEntries) {
            throw new MapException("Array map index out of bounds: {$idx}");
        }
        $this->data[$idx] = $value;
    }

    public function increment(int $key, int $delta = 1): int
    {
        $val = ((int)$this->get($key)) + $delta;
        $this->set($key, $val);
        return $val;
    }

    public function delete(int|string $key): bool
    {
        $idx = (int)$key;
        if ($idx >= 0 && $idx < $this->maxEntries) {
            $this->data[$idx] = 0;
            return true;
        }
        return false;
    }

    public function count(): int
    {
        return $this->maxEntries;
    }

    public function clear(): void
    {
        $this->data = array_fill(0, $this->maxEntries, 0);
    }

    public function all(): array
    {
        return $this->data;
    }
}
