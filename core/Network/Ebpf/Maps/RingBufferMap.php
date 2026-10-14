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
 * eBPF Ring Buffer Map (BPF_MAP_TYPE_RINGBUF).
 * High-throughput asynchronous lock-free event streaming channel between eBPF and userspace.
 */
class RingBufferMap implements MapInterface
{
    private string $name;
    private int $maxEntries;
    /** @var array<int, mixed> */
    private array $buffer = [];
    private int $head = 0;
    private int $tail = 0;

    public function __construct(string $name, int $maxEntries = 4096)
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
        return MapType::RING_BUFFER;
    }

    public function getMaxEntries(): int
    {
        return $this->maxEntries;
    }

    public function push(mixed $event): bool
    {
        if (count($this->buffer) >= $this->maxEntries) {
            // Drop or overwrite oldest
            array_shift($this->buffer);
        }
        $this->buffer[] = $event;
        return true;
    }

    /**
     * Poll / drain all accumulated events from the ring buffer.
     */
    public function drain(): array
    {
        $events = $this->buffer;
        $this->buffer = [];
        return $events;
    }

    public function get(int|string $key): mixed
    {
        return $this->buffer[(int)$key] ?? null;
    }

    public function set(int|string $key, mixed $value): void
    {
        $this->push($value);
    }

    public function delete(int|string $key): bool
    {
        return false;
    }

    public function count(): int
    {
        return count($this->buffer);
    }

    public function clear(): void
    {
        $this->buffer = [];
    }

    public function all(): array
    {
        return $this->buffer;
    }
}
