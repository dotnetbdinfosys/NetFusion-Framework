<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Injectors;

use NetFusion\Core\Chaos\Contracts\FaultInjectorInterface;

/**
 * Class AbstractFaultInjector
 *
 * Base implementation for chaos fault injectors with probability, telemetry, and lifecycle hooks.
 */
abstract class AbstractFaultInjector implements FaultInjectorInterface
{
    protected string $name;
    protected bool $enabled = true;
    protected float $probability = 1.0;
    protected array $config = [];
    protected array $stats = [
        'invocations' => 0,
        'injected' => 0,
        'skipped' => 0,
        'last_injected_at' => null,
    ];

    public function __construct(string $name, float $probability = 1.0, array $config = [])
    {
        $this->name = $name;
        $this->setProbability($probability);
        $this->config = $config;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getProbability(): float
    {
        return $this->probability;
    }

    public function setProbability(float $probability): static
    {
        $this->probability = max(0.0, min(1.0, $probability));
        return $this;
    }

    public function shouldTrigger(array $context = []): bool
    {
        $this->stats['invocations']++;

        if (!$this->enabled) {
            $this->stats['skipped']++;
            return false;
        }

        // If force flag present in context, honor it
        if (isset($context['force'])) {
            $trigger = (bool) $context['force'];
            if (!$trigger) {
                $this->stats['skipped']++;
            }
            return $trigger;
        }

        if ($this->probability >= 1.0) {
            return true;
        }

        if ($this->probability <= 0.0) {
            $this->stats['skipped']++;
            return false;
        }

        // Random roll between 0 and 1
        $roll = mt_rand(1, 1000000) / 1000000.0;
        $trigger = ($roll <= $this->probability);

        if (!$trigger) {
            $this->stats['skipped']++;
        }

        return $trigger;
    }

    protected function recordInjection(): void
    {
        $this->stats['injected']++;
        $this->stats['last_injected_at'] = microtime(true);
    }

    public function getStats(): array
    {
        return array_merge($this->stats, [
            'name' => $this->name,
            'enabled' => $this->enabled,
            'probability' => $this->probability,
            'config' => $this->config,
        ]);
    }

    public function resetStats(): void
    {
        $this->stats = [
            'invocations' => 0,
            'injected' => 0,
            'skipped' => 0,
            'last_injected_at' => null,
        ];
    }

    public function rollback(): void
    {
        // Default no-op for stateless injectors
    }
}
