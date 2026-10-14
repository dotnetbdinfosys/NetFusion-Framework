<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Profiling;

/**
 * Class ProfileSample
 *
 * Encapsulates execution timing, memory consumption deltas, and stack trace frames.
 */
class ProfileSample
{
    protected string $label;
    protected float $wallDurationMs;
    protected int $memoryDeltaBytes;
    protected int $memoryPeakBytes;
    protected float $cpuTimeMs;
    protected array $stackTrace;
    protected float $timestamp;

    public function __construct(
        string $label,
        float $wallDurationMs,
        int $memoryDeltaBytes,
        int $memoryPeakBytes,
        float $cpuTimeMs = 0.0,
        array $stackTrace = [],
        ?float $timestamp = null
    ) {
        $this->label = $label;
        $this->wallDurationMs = round($wallDurationMs, 3);
        $this->memoryDeltaBytes = $memoryDeltaBytes;
        $this->memoryPeakBytes = $memoryPeakBytes;
        $this->cpuTimeMs = round($cpuTimeMs, 3);
        $this->stackTrace = $stackTrace;
        $this->timestamp = $timestamp ?? microtime(true);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getWallDurationMs(): float
    {
        return $this->wallDurationMs;
    }

    public function getMemoryDeltaBytes(): int
    {
        return $this->memoryDeltaBytes;
    }

    public function getMemoryPeakBytes(): int
    {
        return $this->memoryPeakBytes;
    }

    public function getCpuTimeMs(): float
    {
        return $this->cpuTimeMs;
    }

    public function getStackTrace(): array
    {
        return $this->stackTrace;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Format stack trace into collapsed text format for FlameGraph generation.
     * Format: `root;Class::method;subfunction 1`
     */
    public function toCollapsedStack(int $weight = 1): string
    {
        $frames = [$this->label];

        foreach (array_reverse($this->stackTrace) as $frame) {
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $func = $frame['function'] ?? '';
            if ($func !== '') {
                $frames[] = ($class !== '') ? "{$class}{$type}{$func}" : $func;
            }
        }

        // Weight can be duration (in ms) or sample count
        $sampleWeight = max(1, (int) round($this->wallDurationMs > 0 ? $this->wallDurationMs : $weight));
        return implode(';', $frames) . " {$sampleWeight}";
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'wall_duration_ms' => $this->wallDurationMs,
            'memory_delta_bytes' => $this->memoryDeltaBytes,
            'memory_peak_bytes' => $this->memoryPeakBytes,
            'cpu_time_ms' => $this->cpuTimeMs,
            'stack_depth' => count($this->stackTrace),
            'timestamp' => $this->timestamp,
        ];
    }
}
