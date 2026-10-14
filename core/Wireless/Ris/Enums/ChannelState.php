<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Wireless channel conditions and propagation states.
 *
 * @package    NetFusion\Wireless\Ris\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Enums;

enum ChannelState: string
{
    case LineOfSight        = 'los';                 // Direct free-space dominant path
    case Obstructed         = 'nlos_obstructed';     // Severe building / physical blockage
    case MolecularAbsorbed  = 'molecular_absorbed';  // Strong THz water-vapor resonance absorption
    case DeepFade           = 'deep_fade';           // Severe destructive multipath interference

    /**
     * Additional attenuation penalty in dB.
     */
    public function attenuationPenaltyDb(): float
    {
        return match ($this) {
            self::LineOfSight        => 0.0,
            self::Obstructed         => 25.0,
            self::MolecularAbsorbed  => 15.0,
            self::DeepFade           => 35.0,
        };
    }
}

