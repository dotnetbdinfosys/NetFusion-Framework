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

namespace NetFusion\Core\DataWarehouse\Cube;

class HierarchyNavigator
{
    /**
     * @param array<string, array<string>> $dimensionHierarchies Dimension => Levels (e.g. ['dim_date' => ['year', 'quarter', 'month', 'day']])
     */
    public function __construct(
        protected array $dimensionHierarchies = []
    ) {
    }

    /**
     * Get the next lower level in the hierarchy (Drill Down).
     */
    public function getDrillDownLevel(string $dimension, string $currentLevel): ?string
    {
        $levels = $this->dimensionHierarchies[$dimension] ?? [];
        $idx = array_search($currentLevel, $levels, true);
        if ($idx !== false && isset($levels[$idx + 1])) {
            return $levels[$idx + 1];
        }
        return null;
    }

    /**
     * Get the next higher level in the hierarchy (Roll Up).
     */
    public function getRollUpLevel(string $dimension, string $currentLevel): ?string
    {
        $levels = $this->dimensionHierarchies[$dimension] ?? [];
        $idx = array_search($currentLevel, $levels, true);
        if ($idx !== false && $idx > 0 && isset($levels[$idx - 1])) {
            return $levels[$idx - 1];
        }
        return null;
    }

    /**
     * @return array<string>
     */
    public function getLevels(string $dimension): array
    {
        return $this->dimensionHierarchies[$dimension] ?? [];
    }
}
