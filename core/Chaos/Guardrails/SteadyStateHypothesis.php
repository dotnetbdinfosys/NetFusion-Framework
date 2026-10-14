<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Guardrails;

/**
 * Class SteadyStateHypothesis
 *
 * Defines the criteria for a healthy steady-state system before, during, and after chaos injection.
 * If steady state is violated during an experiment, immediate abort is triggered.
 */
class SteadyStateHypothesis
{
    protected string $title;
    protected ?float $maxErrorRatePercent;
    protected ?float $maxP99LatencyMs;
    protected ?float $minSuccessRatePercent;
    /** @var array<string, callable> */
    protected array $customProbes = [];
    protected array $violations = [];

    public function __construct(
        string $title = 'Default Steady State Hypothesis',
        ?float $maxErrorRatePercent = 5.0,
        ?float $maxP99LatencyMs = 1000.0,
        ?float $minSuccessRatePercent = 95.0
    ) {
        $this->title = $title;
        $this->maxErrorRatePercent = $maxErrorRatePercent;
        $this->maxP99LatencyMs = $maxP99LatencyMs;
        $this->minSuccessRatePercent = $minSuccessRatePercent;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function addProbe(string $name, callable $probe): static
    {
        $this->customProbes[$name] = $probe;
        return $this;
    }

    public function getMaxErrorRatePercent(): ?float
    {
        return $this->maxErrorRatePercent;
    }

    public function setMaxErrorRatePercent(?float $rate): static
    {
        $this->maxErrorRatePercent = $rate;
        return $this;
    }

    public function getMaxP99LatencyMs(): ?float
    {
        return $this->maxP99LatencyMs;
    }

    public function setMaxP99LatencyMs(?float $ms): static
    {
        $this->maxP99LatencyMs = $ms;
        return $this;
    }

    public function getMinSuccessRatePercent(): ?float
    {
        return $this->minSuccessRatePercent;
    }

    public function setMinSuccessRatePercent(?float $rate): static
    {
        $this->minSuccessRatePercent = $rate;
        return $this;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * Evaluate steady-state criteria against telemetry data and custom probes.
     *
     * @param array $metrics e.g. ['error_rate' => 1.2, 'p99_latency_ms' => 120, 'success_rate' => 98.8]
     */
    public function evaluate(array $metrics = [], array $context = []): bool
    {
        $this->violations = [];

        // Check error rate
        if ($this->maxErrorRatePercent !== null && isset($metrics['error_rate'])) {
            $errRate = (float) $metrics['error_rate'];
            if ($errRate > $this->maxErrorRatePercent) {
                $this->violations[] = "Error rate {$errRate}% exceeds maximum allowed {$this->maxErrorRatePercent}%";
            }
        }

        // Check p99 latency
        if ($this->maxP99LatencyMs !== null && isset($metrics['p99_latency_ms'])) {
            $p99 = (float) $metrics['p99_latency_ms'];
            if ($p99 > $this->maxP99LatencyMs) {
                $this->violations[] = "P99 latency {$p99}ms exceeds threshold {$this->maxP99LatencyMs}ms";
            }
        }

        // Check success rate
        if ($this->minSuccessRatePercent !== null && isset($metrics['success_rate'])) {
            $succRate = (float) $metrics['success_rate'];
            if ($succRate < $this->minSuccessRatePercent) {
                $this->violations[] = "Success rate {$succRate}% is below minimum required {$this->minSuccessRatePercent}%";
            }
        }

        // Execute custom probes
        foreach ($this->customProbes as $name => $probe) {
            try {
                $result = $probe($context, $metrics);
                if ($result === false) {
                    $this->violations[] = "Custom probe '{$name}' returned false";
                }
            } catch (\Throwable $e) {
                $this->violations[] = "Custom probe '{$name}' threw exception: " . $e->getMessage();
            }
        }

        return empty($this->violations);
    }
}
