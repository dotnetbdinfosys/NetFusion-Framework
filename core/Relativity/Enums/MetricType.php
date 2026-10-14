<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Spacetime metric classifications and geometric background models.
 *
 * @package    NetFusion\Core\Relativity\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Enums;

enum MetricType: string
{
    case Minkowski     = 'minkowski';      // Flat 4D spacetime diag(-1, 1, 1, 1)
    case Schwarzschild = 'schwarzschild';  // Spherically symmetric static black hole
    case Kerr          = 'kerr';           // Axially symmetric rotating black hole
    case Flrw          = 'flrw';           // Friedmann-Lemaitre-Robertson-Walker cosmological metric

    /**
     * Human-friendly description.
     */
    public function description(): string
    {
        return match ($this) {
            self::Minkowski     => 'Flat Spacetime Minkowski Metric',
            self::Schwarzschild => 'Static Spherically Symmetric Schwarzschild Black Hole',
            self::Kerr          => 'Rotating Stationary Axisymmetric Kerr Black Hole',
            self::Flrw          => 'Homogeneous & Isotropic FLRW Expanding Cosmological Metric',
        };
    }
}
