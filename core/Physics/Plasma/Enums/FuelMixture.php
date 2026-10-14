<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Thermonuclear fusion reaction fuel mixture classifications.
 *
 * @package    NetFusion\Core\Physics\Plasma\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma\Enums;

enum FuelMixture: string
{
    case D_T         = 'd_t';          // 2H + 3H -> 4He (3.52 MeV) + n (14.07 MeV)
    case D_D_NEUTRON = 'd_d_n';        // 2H + 2H -> 3He (0.82 MeV) + n (2.45 MeV)
    case D_D_PROTON  = 'd_d_p';        // 2H + 2H -> 3H (1.01 MeV) + p (3.02 MeV)
    case D_HE3       = 'd_he3';        // 2H + 3He -> 4He (3.67 MeV) + p (14.68 MeV)
    case P_B11       = 'p_b11';        // 1H + 11B -> 3 4He (8.68 MeV) - Aneutronic

    /**
     * Total reaction energy released in Mega Electron-Volts (MeV).
     */
    public function reactionEnergyMeV(): float
    {
        return match ($this) {
            self::D_T         => 17.59,
            self::D_D_NEUTRON => 3.27,
            self::D_D_PROTON  => 4.03,
            self::D_HE3       => 18.35,
            self::P_B11       => 8.68,
        };
    }

    /**
     * Alpha particle / charged product energy fraction retained in plasma for self-heating.
     */
    public function chargedFraction(): float
    {
        return match ($this) {
            self::D_T         => 3.52 / 17.59, // ~20%
            self::D_D_NEUTRON => 0.82 / 3.27,  // ~25%
            self::D_D_PROTON  => 4.03 / 4.03,  // 100%
            self::D_HE3       => 18.35 / 18.35,// 100%
            self::P_B11       => 1.0,          // 100%
        };
    }

    /**
     * Optimal plasma ion temperature in kilo-electronvolts (keV) for peak reactivity.
     */
    public function optimalTemperatureKeV(): float
    {
        return match ($this) {
            self::D_T         => 14.0,
            self::D_D_NEUTRON => 50.0,
            self::D_D_PROTON  => 50.0,
            self::D_HE3       => 65.0,
            self::P_B11       => 200.0,
        };
    }

    /**
     * Whether the fusion reaction is aneutronic (virtually zero harmful fast neutron radiation).
     */
    public function isAneutronic(): bool
    {
        return match ($this) {
            self::P_B11, self::D_HE3 => true,
            default => false,
        };
    }
}
