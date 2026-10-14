<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Exception thrown when lattice velocities or relaxation times cause numerical instability.
 *
 * @package    NetFusion\Core\Physics\Fluid\Exceptions
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Exceptions;

class UnstableLatticeException extends LbmSimulationException
{
}
