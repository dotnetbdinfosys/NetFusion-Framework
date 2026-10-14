<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

/**
 * Class MemoryPressureInjector
 *
 * Allocates controlled memory buffers to stress memory consumption and test out-of-memory resilience.
 */
class MemoryPressureInjector extends AbstractFaultInjector
{
    protected int $sizeBytes;
    protected int $maxCapBytes;
    /** @var array<int, string> */
    protected array $buffers = [];

    public function __construct(
        string $name = 'memory_pressure_injector',
        int $sizeBytes = 2 * 1024 * 1024, // 2MB default
        int $maxCapBytes = 32 * 1024 * 1024, // 32MB max safety cap
        float $probability = 1.0,
        array $config = []
    ) {
        parent::__construct($name, $probability, $config);
        $this->sizeBytes = max(1024, $sizeBytes);
        $this->maxCapBytes = max($this->sizeBytes, $maxCapBytes);
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $bytes): static
    {
        $this->sizeBytes = max(1024, min($bytes, $this->maxCapBytes));
        return $this;
    }

    public function getMaxCapBytes(): int
    {
        return $this->maxCapBytes;
    }

    public function getBufferCount(): int
    {
        return count($this->buffers);
    }

    public function getAllocatedBytes(): int
    {
        $total = 0;
        foreach ($this->buffers as $buf) {
            $total += strlen($buf);
        }
        return $total;
    }

    public function inject(array $context = []): array
    {
        $targetBytes = isset($context['size_bytes']) ? (int) $context['size_bytes'] : $this->sizeBytes;
        $targetBytes = min($targetBytes, $this->maxCapBytes);

        $memBefore = memory_get_usage(true);

        // Allocate block with random byte pattern
        $buffer = str_repeat('X', $targetBytes);
        $this->buffers[] = $buffer;

        $memAfter = memory_get_usage(true);
        $this->recordInjection();

        return [
            'injector' => $this->name,
            'allocated_bytes' => $targetBytes,
            'total_held_bytes' => $this->getAllocatedBytes(),
            'memory_before' => $memBefore,
            'memory_after' => $memAfter,
            'timestamp' => microtime(true),
        ];
    }

    public function rollback(): void
    {
        $this->buffers = [];
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
