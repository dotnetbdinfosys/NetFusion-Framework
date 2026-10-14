<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Discrete lattice topology model classification (D2Q9, D2Q5, D3Q19).
 *
 * @package    NetFusion\Core\Physics\Fluid\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Enums;

enum LatticeModel: string
{
    case D2Q9  = 'D2Q9';
    case D2Q5  = 'D2Q5';
    case D3Q19 = 'D3Q19';

    /**
     * Get spatial dimensions (2 or 3).
     */
    public function dimensions(): int
    {
        return match ($this) {
            self::D2Q9, self::D2Q5 => 2,
            self::D3Q19 => 3,
        };
    }

    /**
     * Get discrete velocity directions count.
     */
    public function directionsCount(): int
    {
        return match ($this) {
            self::D2Q5 => 5,
            self::D2Q9 => 9,
            self::D3Q19 => 19,
        };
    }

    /**
     * Get speed of sound squared ($c_s^2$).
     */
    public function soundSpeedSquared(): float
    {
        return 1.0 / 3.0;
    }
}
