<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Hydrodynamic boundary condition classifications.
 *
 * @package    NetFusion\Core\Physics\Fluid\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Enums;

enum BoundaryConditionType: string
{
    case BOUNCE_BACK        = 'bounce_back';       // No-slip solid wall
    case PERIODIC           = 'periodic';          // Toroidal wrap-around flow
    case DIRICHLET_VELOCITY = 'dirichlet_velocity';// Prescribed velocity (moving lid / inlet)
    case NEUMANN_PRESSURE   = 'neumann_pressure';  // Open zero-gradient outlet

    /**
     * Determine if boundary interacts with solid stationary surfaces.
     */
    public function isSolid(): bool
    {
        return $this === self::BOUNCE_BACK;
    }
}
