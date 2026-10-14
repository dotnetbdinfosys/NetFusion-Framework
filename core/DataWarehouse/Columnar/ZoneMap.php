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

namespace NetFusion\Core\DataWarehouse\Columnar;

class ZoneMap
{
    protected mixed $minValue = null;
    protected mixed $maxValue = null;
    protected int $nullCount = 0;
    protected int $rowCount = 0;

    /**
     * @param mixed $minValue
     * @param mixed $maxValue
     * @param int $nullCount
     * @param int $rowCount
     */
    public function __construct(
        mixed $minValue = null,
        mixed $maxValue = null,
        int $nullCount = 0,
        int $rowCount = 0
    ) {
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        $this->nullCount = $nullCount;
        $this->rowCount = $rowCount;
    }

    /**
     * Update ZoneMap with a single value.
     */
    public function add(mixed $value): void
    {
        $this->rowCount++;
        if ($value === null) {
            $this->nullCount++;
            return;
        }

        if ($this->minValue === null || $value < $this->minValue) {
            $this->minValue = $value;
        }
        if ($this->maxValue === null || $value > $this->maxValue) {
            $this->maxValue = $value;
        }
    }

    /**
     * Update ZoneMap with a batch of values.
     *
     * @param array<int, mixed> $values
     */
    public function addBatch(array $values): void
    {
        foreach ($values as $val) {
            $this->add($val);
        }
    }

    public function getMin(): mixed
    {
        return $this->minValue;
    }

    public function getMax(): mixed
    {
        return $this->maxValue;
    }

    public function getNullCount(): int
    {
        return $this->nullCount;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Evaluate whether a filter condition CAN possibly match values in this ZoneMap.
     * Returns false if this block can be safely SKIPPED entirely.
     *
     * @param string $operator '=', '!=', '>', '>=', '<', '<=', 'BETWEEN', 'IN'
     * @param mixed $value
     * @return bool True if block might contain matches, False if block can be definitely skipped
     */
    public function canMatch(string $operator, mixed $value): bool
    {
        if ($this->rowCount === 0) {
            return false;
        }

        if ($this->minValue === null && $this->maxValue === null) {
            return $operator === 'IS_NULL';
        }

        $op = strtoupper(trim($operator));

        switch ($op) {
            case '=':
            case '==':
                if (is_string($value) && is_string($this->minValue) && is_string($this->maxValue)) {
                    return (strcmp($value, $this->minValue) >= 0 && strcmp($value, $this->maxValue) <= 0);
                }
                return ($value >= $this->minValue && $value <= $this->maxValue);

            case '!=':
            case '<>':
                // Only skip if all rows are identical and equal to $value
                return !($this->minValue === $this->maxValue && $this->minValue === $value);

            case '>':
                if (is_string($value) && is_string($this->maxValue)) {
                    return strcmp($this->maxValue, $value) > 0;
                }
                return ($this->maxValue > $value);

            case '>=':
                if (is_string($value) && is_string($this->maxValue)) {
                    return strcmp($this->maxValue, $value) >= 0;
                }
                return ($this->maxValue >= $value);

            case '<':
                if (is_string($value) && is_string($this->minValue)) {
                    return strcmp($this->minValue, $value) < 0;
                }
                return ($this->minValue < $value);

            case '<=':
                if (is_string($value) && is_string($this->minValue)) {
                    return strcmp($this->minValue, $value) <= 0;
                }
                return ($this->minValue <= $value);

            case 'BETWEEN':
                if (is_array($value) && count($value) >= 2) {
                    $low = $value[0];
                    $high = $value[1];
                    return !($this->maxValue < $low || $this->minValue > $high);
                }
                return true;

            case 'IN':
                if (is_array($value)) {
                    foreach ($value as $v) {
                        if ($v >= $this->minValue && $v <= $this->maxValue) {
                            return true;
                        }
                    }
                    return false;
                }
                return true;

            default:
                return true; // Conservative fallback: inspect block
        }
    }

    /**
     * @return array{min: mixed, max: mixed, null_count: int, row_count: int}
     */
    public function toArray(): array
    {
        return [
            'min' => $this->minValue,
            'max' => $this->maxValue,
            'null_count' => $this->nullCount,
            'row_count' => $this->rowCount,
        ];
    }
}
