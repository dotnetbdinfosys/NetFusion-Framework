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

use NetFusion\Core\Telemetry\Contracts\GaugeInterface;

class Gauge extends AbstractMetric implements GaugeInterface
{
    /** @var array<string, array{value: float|int, labels: array<string, string>}> */
    protected array $values = [];

    public function getType(): string
    {
        return 'gauge';
    }

    public function set(float|int $value, array $labels = []): self
    {
        $key = $this->getLabelKey($labels);
        $this->values[$key] = [
            'value' => $value,
            'labels' => $this->normalizeLabels($labels),
        ];

        return $this;
    }

    public function inc(float|int $amount = 1, array $labels = []): self
    {
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

    public function dec(float|int $amount = 1, array $labels = []): self
    {
        return $this->inc(-$amount, $labels);
    }

    public function setToCurrentTime(array $labels = []): self
    {
        return $this->set(time(), $labels);
    }

    public function get(array $labels = []): float|int
    {
        $key = $this->getLabelKey($labels);
        return $this->values[$key]['value'] ?? 0;
    }

    public function collect(): array
    {
        $samples = [];
        foreach ($this->values as $item) {
            $samples[] = new MetricSample(
                name: $this->name,
                value: $item['value'],
                labels: $item['labels']
            );
        }

        if (empty($samples)) {
            $samples[] = new MetricSample(
                name: $this->name,
                value: 0,
                labels: []
            );
        }

        return $samples;
    }

    public function reset(): void
    {
        $this->values = [];
    }
}
