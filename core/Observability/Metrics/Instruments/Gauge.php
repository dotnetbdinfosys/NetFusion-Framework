<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Metrics\Instruments;

/**
 * Class Gauge
 *
 * Instantaneous point-in-time value instrument (e.g. CPU load, memory usage, temperature).
 */
class Gauge extends AbstractInstrument
{
    protected string $type = 'gauge';
    /** @var array<string, array{value: float|int, attributes: array, last_updated: float}> */
    protected array $series = [];

    public function record(float|int $value, array $attributes = []): void
    {
        $key = $this->attributeKey($attributes);

        $this->series[$key] = [
            'value' => $value,
            'attributes' => $attributes,
            'last_updated' => microtime(true),
        ];
    }

    public function getValue(array $attributes = []): float|int
    {
        $key = $this->attributeKey($attributes);
        return $this->series[$key]['value'] ?? 0;
    }

    public function getData(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'type' => $this->type,
            'points' => array_values($this->series),
        ];
    }

    public function reset(): void
    {
        $this->series = [];
    }
}
