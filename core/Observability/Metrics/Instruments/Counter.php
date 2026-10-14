<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Metrics\Instruments;

/**
 * Class Counter
 *
 * Monotonically increasing metric instrument (e.g. total requests, bytes transferred).
 */
class Counter extends AbstractInstrument
{
    protected string $type = 'counter';
    /** @var array<string, array{value: float|int, attributes: array, last_updated: float}> */
    protected array $series = [];

    public function add(float|int $amount = 1, array $attributes = []): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Counter value must be non-negative (received {$amount}).");
        }

        $key = $this->attributeKey($attributes);

        if (!isset($this->series[$key])) {
            $this->series[$key] = [
                'value' => 0,
                'attributes' => $attributes,
                'last_updated' => microtime(true),
            ];
        }

        $this->series[$key]['value'] += $amount;
        $this->series[$key]['last_updated'] = microtime(true);
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
