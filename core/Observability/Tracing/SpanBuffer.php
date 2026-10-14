<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Tracing;

/**
 * Class SpanBuffer
 *
 * Thread-safe, bounded in-memory buffer storing completed spans awaiting export.
 */
class SpanBuffer
{
    protected int $capacity;
    /** @var array<int, Span> */
    protected array $spans = [];

    public function __construct(int $capacity = 1000)
    {
        $this->capacity = max(1, $capacity);
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function push(Span $span): void
    {
        // If at capacity, drop the oldest span
        if (count($this->spans) >= $this->capacity) {
            array_shift($this->spans);
        }
        $this->spans[] = $span;
    }

    /**
     * Drain and retrieve all buffered spans, clearing the buffer.
     *
     * @return array<int, Span>
     */
    public function drain(): array
    {
        $drained = $this->spans;
        $this->spans = [];
        return $drained;
    }

    /**
     * Peek at buffered spans without clearing.
     *
     * @return array<int, Span>
     */
    public function all(): array
    {
        return $this->spans;
    }

    public function count(): int
    {
        return count($this->spans);
    }

    public function clear(): void
    {
        $this->spans = [];
    }
}
