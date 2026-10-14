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

namespace NetFusion\Core\DataWarehouse\Contracts;

interface CubeInterface
{
    /**
     * Get cube name.
     */
    public function getName(): string;

    /**
     * Slice cube along a single dimension with a specific value.
     *
     * @param string $dimension
     * @param mixed $value
     * @return static
     */
    public function slice(string $dimension, mixed $value): static;

    /**
     * Dice cube along multiple dimensions with multiple values.
     *
     * @param array<string, array<mixed>> $predicates
     * @return static
     */
    public function dice(array $predicates): static;

    /**
     * Drill down into a deeper hierarchy level of a dimension.
     *
     * @param string $dimension
     * @param string $level
     * @return static
     */
    public function drillDown(string $dimension, string $level): static;

    /**
     * Roll up to a higher hierarchy level of a dimension.
     *
     * @param string $dimension
     * @param string $level
     * @return static
     */
    public function rollUp(string $dimension, string $level): static;

    /**
     * Pivot cube into cross-tabulated dataset.
     *
     * @param array<string> $rowDimensions
     * @param array<string> $colDimensions
     * @param array<string, string> $measures
     * @return array<string, mixed>
     */
    public function pivot(array $rowDimensions, array $colDimensions, array $measures): array;
}
