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

interface HistogramInterface extends MetricInterface
{
    /**
     * Record a distribution observation value.
     *
     * @param float|int $value
     * @param array<string, string|int|float|bool> $labels
     * @return self
     */
    public function record(float|int $value, array $labels = []): self;

    /**
     * Observe execution duration of callable in seconds/milliseconds and return the result.
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, string|int|float|bool> $labels
     * @return T
     */
    public function time(callable $callback, array $labels = []): mixed;

    /**
     * Get configured bucket boundaries.
     *
     * @return float[]
     */
    public function getBuckets(): array;
}
