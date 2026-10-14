<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Metrics\Instruments;

/**
 * Class AbstractInstrument
 *
 * Base implementation for OpenTelemetry instruments with attribute hashing.
 */
abstract class AbstractInstrument implements InstrumentInterface
{
    protected string $name;
    protected string $description;
    protected string $unit;
    protected string $type;

    public function __construct(string $name, string $description = '', string $unit = '1')
    {
        $this->name = $name;
        $this->description = $description;
        $this->unit = $unit;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Compute a deterministic key for an attributes dictionary.
     */
    protected function attributeKey(array $attributes): string
    {
        if (empty($attributes)) {
            return '__default__';
        }
        ksort($attributes);
        return md5(serialize($attributes));
    }
}
