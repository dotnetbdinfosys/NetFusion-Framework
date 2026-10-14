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

namespace NetFusion\Core\Telemetry\Metrics;

use InvalidArgumentException;
use NetFusion\Core\Telemetry\Contracts\CounterInterface;

class Counter extends AbstractMetric implements CounterInterface
{
    /** @var array<string, array{value: float|int, labels: array<string, string>}> */
    protected array $values = [];

    public function getType(): string
    {
        return 'counter';
    }

    public function add(float|int $amount = 1, array $labels = []): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException("Counter cannot be decreased. Value must be >= 0, [{$amount}] given.");
        }

        $key = $this->getLabelKey($labels);
        if (!isset($this->values[$key])) {
            $this->values[$key] = [
                'value' => 0,
                'labels' => $this->normalizeLabels($labels),
            ];
        }

        $this->values[$key]['value'] += $amount;
        return $this;
    }

    public function inc(array $labels = []): self
    {
        return $this->add(1, $labels);
    }

    public function get(array $labels = []): float|int
    {
        $key = $this->getLabelKey($labels);
        return $this->values[$key]['value'] ?? 0;
    }

    public function collect(): array
    {
        $samples = [];
        $suffix = str_ends_with($this->name, '_total') ? null : '_total';

        foreach ($this->values as $item) {
            $samples[] = new MetricSample(
                name: $this->name,
                value: $item['value'],
                labels: $item['labels'],
                suffix: $suffix
            );
        }

        // If no values recorded yet, return zero baseline
        if (empty($samples)) {
            $samples[] = new MetricSample(
                name: $this->name,
                value: 0,
                labels: [],
                suffix: $suffix
            );
        }

        return $samples;
    }

    public function reset(): void
    {
        $this->values = [];
    }
}
