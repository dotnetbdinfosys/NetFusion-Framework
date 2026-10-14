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

interface CounterInterface extends MetricInterface
{
    /**
     * Increment the counter by value with optional label dimensions.
     *
     * @param float|int $amount
     * @param array<string, string|int|float|bool> $labels
     * @return self
     */
    public function add(float|int $amount = 1, array $labels = []): self;

    /**
     * Increment by 1 with optional labels.
     *
     * @param array<string, string|int|float|bool> $labels
     * @return self
     */
    public function inc(array $labels = []): self;

    /**
     * Get the current counter value for a given label combination.
     *
     * @param array<string, string|int|float|bool> $labels
     * @return float|int
     */
    public function get(array $labels = []): float|int;
}
