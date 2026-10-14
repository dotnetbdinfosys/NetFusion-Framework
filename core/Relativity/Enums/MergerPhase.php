<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Binary coalescence merger phases.
 *
 * @package    NetFusion\Core\Relativity\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Enums;

enum MergerPhase: string
{
    case Inspiral = 'inspiral'; // Quasi-circular adiabatic orbital decay
    case Plunge   = 'plunge';   // Dynamic instability crossing Innermost Stable Circular Orbit (ISCO)
    case Merger   = 'merger';   // Apparent horizon coalescence & maximum GW amplitude
    case Ringdown = 'ringdown'; // Perturbed remnant black hole quasinormal damped oscillation

    /**
     * Human-friendly description.
     */
    public function description(): string
    {
        return match ($this) {
            self::Inspiral => 'Post-Newtonian Adiabatic Inspiral',
            self::Plunge   => 'ISCO Dynamic Instability Plunge',
            self::Merger   => 'Non-Linear Horizon Coalescence Peak',
            self::Ringdown => 'Quasinormal Mode (QNM) Damped Ringdown',
        };
    }
}
