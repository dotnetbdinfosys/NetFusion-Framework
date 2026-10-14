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

namespace NetFusion\Core\DataWarehouse\BI;

class CalculatedMeasure
{
    /**
     * @param string $name Measure identifier (e.g. 'profit_margin_pct', 'avg_order_value')
     * @param \Closure $formula Formula closure: fn(array $metrics): float
     * @param string $format Formatting style ('percent', 'currency', 'number')
     */
    public function __construct(
        protected string $name,
        protected \Closure $formula,
        protected string $format = 'number'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Calculate value from aggregated row/cell metrics.
     *
     * @param array<string, mixed> $metrics
     * @return float
     */
    public function compute(array $metrics): float
    {
        return (float)call_user_func($this->formula, $metrics);
    }
}
