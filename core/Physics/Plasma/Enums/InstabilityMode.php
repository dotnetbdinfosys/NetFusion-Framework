<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Fabric
 *
 * Magnetohydrodynamic (MHD) plasma instability modes.
 *
 * @package    NetFusion\Core\Physics\Plasma\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Plasma\Enums;

enum InstabilityMode: string
{
    case KINK       = 'kink';       // m=1, n=1 helical column deformation
    case SAUSAGE    = 'sausage';    // m=0, n=0 pinch necking
    case BALLOONING = 'ballooning'; // High-beta bad curvature pressure-driven
    case TEARING    = 'tearing';    // Resistive magnetic reconnection islands

    public function isMhdDisruptive(): bool
    {
        return match ($this) {
            self::KINK, self::BALLOONING => true,
            default => false,
        };
    }

    public function poloidalModeNumber(): int
    {
        return match ($this) {
            self::SAUSAGE => 0,
            self::KINK => 1,
            self::TEARING => 2,
            self::BALLOONING => 10,
        };
    }
}
