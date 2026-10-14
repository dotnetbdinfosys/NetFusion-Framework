<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis (TDA) Engine
 *
 * Homology dimensions for topological feature tracking.
 *
 * @package    NetFusion\Core\Analytics\Tda\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Analytics\Tda\Enums;

enum HomologyDimension: int
{
    case H0 = 0; // Connected components / Clusters
    case H1 = 1; // 1D Topological loops / tunnels
    case H2 = 2; // 2D Voids / Cavities

    public function featureName(): string
    {
        return match ($this) {
            self::H0 => 'Connected Components (Clusters)',
            self::H1 => '1D Topological Loops (Tunnels)',
            self::H2 => '2D Voids (Cavities)',
        };
    }
}
