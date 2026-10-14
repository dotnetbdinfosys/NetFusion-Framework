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

use JsonSerializable;

class MetricSample implements JsonSerializable
{
    /**
     * @param string $name
     * @param float|int $value
     * @param array<string, string|int|float|bool> $labels
     * @param float|null $timestamp
     * @param string|null $suffix (e.g. '_bucket', '_sum', '_count', '_total')
     */
    public function __construct(
        public string $name,
        public float|int $value,
        public array $labels = [],
        public ?float $timestamp = null,
        public ?string $suffix = null
    ) {
        $this->timestamp = $timestamp ?? microtime(true);
    }

    public function getMetricName(): string
    {
        return $this->suffix ? "{$this->name}{$this->suffix}" : $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getMetricName(),
            'value' => $this->value,
            'labels' => $this->labels,
            'timestamp' => $this->timestamp,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
