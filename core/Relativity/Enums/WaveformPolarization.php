<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Gravitational wave tensor polarizations.
 *
 * @package    NetFusion\Core\Relativity\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Enums;

enum WaveformPolarization: string
{
    case Plus  = 'plus';   // h+ (plus polarization)
    case Cross = 'cross';  // hx (cross polarization)

    /**
     * Angular dependence on orbital inclination angle iota (in radians).
     */
    public function inclinationFactor(float $iotaRad): float
    {
        $cosIota = cos($iotaRad);
        return match ($this) {
            self::Plus  => -(1.0 + ($cosIota * $cosIota)) / 2.0,
            self::Cross => -$cosIota,
        };
    }
}
