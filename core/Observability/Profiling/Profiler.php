<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Profiling;

use Throwable;

/**
 * Class Profiler
 *
 * Lightweight continuous wall-clock and memory allocator profiler with FlameGraph export.
 */
class Profiler
{
    /** @var array<string, array> */
    protected array $activeSessions = [];
    /** @var array<int, ProfileSample> */
    protected array $samples = [];
    protected int $maxSamples;

    public function __construct(int $maxSamples = 1000)
    {
        $this->maxSamples = max(10, $maxSamples);
    }

    /**
     * Start profiling a labeled execution section.
     */
    public function start(string $label): string
    {
        $id = uniqid('prof_', true);
        $cpuStart = 0.0;

        if (function_exists('getrusage')) {
            $ru = getrusage();
            $cpuStart = ($ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1e6)
                      + ($ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1e6);
        }

        $this->activeSessions[$id] = [
            'label' => $label,
            'start_time' => microtime(true),
            'start_mem' => memory_get_usage(false),
            'start_cpu' => $cpuStart,
            'stack' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
        ];

        return $id;
    }

    /**
     * Stop profiling the session and record a ProfileSample.
     */
    public function stop(string $sessionId): ProfileSample
    {
        if (!isset($this->activeSessions[$sessionId])) {
            throw new \InvalidArgumentException("Active profiling session '{$sessionId}' not found.");
        }

        $session = $this->activeSessions[$sessionId];
        unset($this->activeSessions[$sessionId]);

        $endTime = microtime(true);
        $endMem = memory_get_usage(false);
        $peakMem = memory_get_peak_usage(false);

        $wallDurationMs = ($endTime - $session['start_time']) * 1000.0;
        $memDelta = $endMem - $session['start_mem'];

        $cpuDurationMs = 0.0;
        if (function_exists('getrusage')) {
            $ru = getrusage();
            $cpuEnd = ($ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1e6)
                    + ($ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1e6);
            $cpuDurationMs = max(0.0, ($cpuEnd - $session['start_cpu']) * 1000.0);
        }

        $sample = new ProfileSample(
            $session['label'],
            $wallDurationMs,
            $memDelta,
            $peakMem,
            $cpuDurationMs,
            $session['stack']
        );

        if (count($this->samples) >= $this->maxSamples) {
            array_shift($this->samples);
        }
        $this->samples[] = $sample;

        return $sample;
    }

    /**
     * Profile a callable execution.
     */
    public function profile(string $label, callable $callable): mixed
    {
        $sessionId = $this->start($label);
        try {
            return $callable();
        } finally {
            $this->stop($sessionId);
        }
    }

    /**
     * @return array<int, ProfileSample>
     */
    public function getSamples(): array
    {
        return $this->samples;
    }

    /**
     * Generate collapsed stack traces for FlameGraph visualization (SpeedScope, Brendan Gregg flamegraphs).
     */
    public function toFlameGraphData(): string
    {
        $lines = [];
        foreach ($this->samples as $sample) {
            $lines[] = $sample->toCollapsedStack();
        }
        return implode("\n", $lines);
    }

    /**
     * Aggregate profiling statistics.
     */
    public function getSummary(): array
    {
        $totalSamples = count($this->samples);
        $totalDurationMs = 0.0;
        $totalMemDelta = 0;
        $maxPeakMem = 0;

        foreach ($this->samples as $sample) {
            $totalDurationMs += $sample->getWallDurationMs();
            $totalMemDelta += $sample->getMemoryDeltaBytes();
            $maxPeakMem = max($maxPeakMem, $sample->getMemoryPeakBytes());
        }

        return [
            'total_samples' => $totalSamples,
            'total_wall_duration_ms' => round($totalDurationMs, 3),
            'net_memory_delta_bytes' => $totalMemDelta,
            'max_peak_memory_bytes' => $maxPeakMem,
            'active_sessions_count' => count($this->activeSessions),
        ];
    }

    public function clear(): void
    {
        $this->activeSessions = [];
        $this->samples = [];
    }
}
