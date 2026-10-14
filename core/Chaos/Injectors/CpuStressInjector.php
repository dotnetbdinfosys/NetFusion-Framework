<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

/**
 * Class CpuStressInjector
 *
 * Generates bounded, controlled CPU load to test thread pool saturation and latency under high compute pressure.
 */
class CpuStressInjector extends AbstractFaultInjector
{
    protected int $durationMs;
    protected int $maxDurationMs;
    protected bool $simulateOnly;

    public function __construct(
        string $name = 'cpu_stress_injector',
        int $durationMs = 50,
        int $maxDurationMs = 2000,
        bool $simulateOnly = false,
        float $probability = 1.0,
        array $config = []
    ) {
        parent::__construct($name, $probability, $config);
        $this->durationMs = max(1, $durationMs);
        $this->maxDurationMs = max($this->durationMs, $maxDurationMs);
        $this->simulateOnly = $simulateOnly;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $ms): static
    {
        $this->durationMs = max(1, min($ms, $this->maxDurationMs));
        return $this;
    }

    public function getMaxDurationMs(): int
    {
        return $this->maxDurationMs;
    }

    public function isSimulateOnly(): bool
    {
        return $this->simulateOnly;
    }

    public function setSimulateOnly(bool $simulate): static
    {
        $this->simulateOnly = $simulate;
        return $this;
    }

    public function inject(array $context = []): array
    {
        $duration = isset($context['duration_ms']) ? (int) $context['duration_ms'] : $this->durationMs;
        $duration = min($duration, $this->maxDurationMs);
        $simulate = isset($context['simulate_only']) ? (bool) $context['simulate_only'] : $this->simulateOnly;

        $iterations = 0;
        $startTime = microtime(true);

        if (!$simulate) {
            $endTime = $startTime + ($duration / 1000.0);
            $seed = "netfusion_chaos_stress_" . microtime(true);
            while (microtime(true) < $endTime) {
                // Perform compute-heavy operations
                $seed = hash('sha256', $seed);
                $iterations++;
            }
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $this->recordInjection();

        return [
            'injector' => $this->name,
            'target_duration_ms' => $duration,
            'elapsed_ms' => $elapsedMs,
            'iterations' => $iterations,
            'simulated' => $simulate,
            'timestamp' => microtime(true),
        ];
    }
}
