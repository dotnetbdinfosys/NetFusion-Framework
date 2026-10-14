<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Fabric
 *
 * Symplectic and numerical N-body dynamical integrator algorithms.
 *
 * @package    NetFusion\Core\Astro\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Astro\Enums;

enum IntegratorType: string
{
    case LEAPFROG    = 'leapfrog';     // 2nd-order symplectic Verlet/Leapfrog
    case YOSHIDA_4TH = 'yoshida_4th';  // 4th-order symplectic composition (Yoshida 1990)
    case RK4         = 'rk4';          // Standard 4th-order Runge-Kutta
    case EULER       = 'euler';        // 1st-order explicit Euler (benchmark baseline)

    /**
     * Whether the integrator algorithm strictly preserves symplectic Hamiltonian phase volume.
     */
    public function isSymplectic(): bool
    {
        return match ($this) {
            self::LEAPFROG, self::YOSHIDA_4TH => true,
            self::RK4, self::EULER => false,
        };
    }

    /**
     * Algebraic integration accuracy order.
     */
    public function order(): int
    {
        return match ($this) {
            self::EULER => 1,
            self::LEAPFROG => 2,
            self::YOSHIDA_4TH, self::RK4 => 4,
        };
    }
}
