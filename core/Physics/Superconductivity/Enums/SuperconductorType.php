<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Thermodynamic classification of superconductors based on Ginzburg-Landau parameter kappa.
 *
 * @package    NetFusion\Core\Physics\Superconductivity\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity\Enums;

enum SuperconductorType: string
{
    case TYPE_I  = 'type_1';  // kappa < 1/sqrt(2): Complete Meissner state, negative surface energy
    case TYPE_II = 'type_2';  // kappa > 1/sqrt(2): Abrikosov mixed vortex state, positive surface energy

    /**
     * Critical Ginzburg-Landau threshold parameter kappa_c = 1 / sqrt(2) ~ 0.7071.
     */
    public function kappaThreshold(): float
    {
        return 1.0 / sqrt(2.0);
    }

    /**
     * Whether the material accommodates quantized Abrikosov magnetic vortex lines.
     */
    public function allowsVortices(): bool
    {
        return match ($this) {
            self::TYPE_II => true,
            self::TYPE_I  => false,
        };
    }
}
