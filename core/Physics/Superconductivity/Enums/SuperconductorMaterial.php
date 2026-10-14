<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Fabric
 *
 * Superconducting material benchmarks and physical material constants.
 *
 * @package    NetFusion\Core\Physics\Superconductivity\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Superconductivity\Enums;

enum SuperconductorMaterial: string
{
    case LEAD   = 'lead';     // Element Pb (Type-I elemental baseline)
    case NBTI   = 'nbti';     // Niobium-Titanium (standard MRI & accelerator magnet wire)
    case NB3SN  = 'nb3sn';    // Niobium-Tin (high-field Tokamak fusion TF coils)
    case YBCO   = 'ybco';     // YBa2Cu3O7-x (REBCO High-Tc Superconductor tape)
    case BSCCO  = 'bscco';    // Bi2Sr2Ca2Cu3O10 (Bi-2223 High-Tc wire)
    case MGB2   = 'mgb2';     // Magnesium Diboride (intermediate Tc)

    /**
     * Critical transition temperature T_c in Kelvin (zero field).
     */
    public function criticalTemperature(): float
    {
        return match ($this) {
            self::LEAD  => 7.20,
            self::NBTI  => 9.20,
            self::NB3SN => 18.30,
            self::MGB2  => 39.00,
            self::YBCO  => 93.00,
            self::BSCCO => 108.00,
        };
    }

    /**
     * Upper critical magnetic field B_c2 (or thermodynamic B_c for Type-I) at T=0 in Tesla.
     */
    public function upperCriticalField0(): float
    {
        return match ($this) {
            self::LEAD  => 0.08,   // B_c
            self::NBTI  => 14.50,  // B_c2
            self::NB3SN => 28.00,  // B_c2
            self::MGB2  => 39.00,  // B_c2
            self::YBCO  => 120.00, // B_c2
            self::BSCCO => 200.00, // B_c2
        };
    }

    /**
     * Zero-temperature London magnetic penetration depth lambda_0 in meters.
     */
    public function penetrationDepth0(): float
    {
        return match ($this) {
            self::LEAD  => 37e-9,
            self::NBTI  => 240e-9,
            self::NB3SN => 65e-9,
            self::MGB2  => 140e-9,
            self::YBCO  => 150e-9,
            self::BSCCO => 200e-9,
        };
    }

    /**
     * Zero-temperature Ginzburg-Landau coherence length xi_0 in meters.
     */
    public function coherenceLength0(): float
    {
        return match ($this) {
            self::LEAD  => 83e-9,
            self::NBTI  => 5.0e-9,
            self::NB3SN => 3.0e-9,
            self::MGB2  => 5.2e-9,
            self::YBCO  => 1.5e-9,
            self::BSCCO => 1.0e-9,
        };
    }

    /**
     * Dimensionless Ginzburg-Landau parameter kappa = lambda_0 / xi_0.
     */
    public function ginzburgLandauKappa(): float
    {
        return $this->penetrationDepth0() / $this->coherenceLength0();
    }

    /**
     * Type classification: Type-I if kappa < 0.7071, else Type-II.
     */
    public function type(): SuperconductorType
    {
        return ($this->ginzburgLandauKappa() > (1.0 / sqrt(2.0)))
            ? SuperconductorType::TYPE_II
            : SuperconductorType::TYPE_I;
    }

    /**
     * Whether operational at liquid nitrogen boiling point (77 K).
     */
    public function isHighTemperature(): bool
    {
        return $this->criticalTemperature() > 77.0;
    }
}
