<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

/**
 * Class LatencyInjector
 *
 * Injects artificial network/service latency and jitter into the request lifecycle.
 */
class LatencyInjector extends AbstractFaultInjector
{
    protected int $latencyMs;
    protected int $jitterMs;
    protected bool $simulateOnly;
    protected int $totalInjectedMs = 0;

    public function __construct(
        string $name = 'latency_injector',
        int $latencyMs = 100,
        int $jitterMs = 0,
        float $probability = 1.0,
        bool $simulateOnly = false,
        array $config = []
    ) {
        parent::__construct($name, $probability, $config);
        $this->latencyMs = max(0, $latencyMs);
        $this->jitterMs = max(0, $jitterMs);
        $this->simulateOnly = $simulateOnly;
    }

    public function getLatencyMs(): int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(int $latencyMs): static
    {
        $this->latencyMs = max(0, $latencyMs);
        return $this;
    }

    public function getJitterMs(): int
    {
        return $this->jitterMs;
    }

    public function setJitterMs(int $jitterMs): static
    {
        $this->jitterMs = max(0, $jitterMs);
        return $this;
    }

    public function isSimulateOnly(): bool
    {
        return $this->simulateOnly;
    }

    public function setSimulateOnly(bool $simulateOnly): static
    {
        $this->simulateOnly = $simulateOnly;
        return $this;
    }

    public function getTotalInjectedMs(): int
    {
        return $this->totalInjectedMs;
    }

    public function inject(array $context = []): array
    {
        $baseLatency = isset($context['latency_ms']) ? (int) $context['latency_ms'] : $this->latencyMs;
        $jitter = isset($context['jitter_ms']) ? (int) $context['jitter_ms'] : $this->jitterMs;

        $jitterOffset = ($jitter > 0) ? mt_rand(-$jitter, $jitter) : 0;
        $actualDelay = max(0, $baseLatency + $jitterOffset);

        $simulate = isset($context['simulate_only']) ? (bool) $context['simulate_only'] : $this->simulateOnly;

        if (!$simulate && $actualDelay > 0) {
            usleep($actualDelay * 1000);
        }

        $this->totalInjectedMs += $actualDelay;
        $this->recordInjection();

        return [
            'injector' => $this->name,
            'injected_latency_ms' => $actualDelay,
            'base_latency_ms' => $baseLatency,
            'jitter_offset_ms' => $jitterOffset,
            'simulated' => $simulate,
            'timestamp' => microtime(true),
        ];
    }

    public function resetStats(): void
    {
        parent::resetStats();
        $this->totalInjectedMs = 0;
    }
}
