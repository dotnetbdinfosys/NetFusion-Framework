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

namespace NetFusion\Core\Telemetry\Contracts;

interface GaugeInterface extends MetricInterface
{
    /**
     * Set the current gauge value for a label combination.
     *
     * @param float|int $value
     * @param array<string, string|int|float|bool> $labels
     * @return self
     */
    public function set(float|int $value, array $labels = []): self;

    /**
     * Increment the gauge value.
     */
    public function inc(float|int $amount = 1, array $labels = []): self;

    /**
     * Decrement the gauge value.
     */
    public function dec(float|int $amount = 1, array $labels = []): self;

    /**
     * Set the gauge value to current unix timestamp in seconds.
     */
    public function setToCurrentTime(array $labels = []): self;

    /**
     * Get the current gauge value.
     *
     * @param array<string, string|int|float|bool> $labels
     * @return float|int
     */
    public function get(array $labels = []): float|int;
}
