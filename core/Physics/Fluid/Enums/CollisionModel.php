<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Boltzmann collision operator formulation (BGK, TRT, MRT).
 *
 * @package    NetFusion\Core\Physics\Fluid\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Enums;

enum CollisionModel: string
{
    case BGK = 'bgk'; // Bhatnagar-Gross-Krook (Single Relaxation Time)
    case TRT = 'trt'; // Two-Relaxation-Time (Symmetric / Anti-symmetric)
    case MRT = 'mrt'; // Multiple-Relaxation-Time (Moment-space transformation)
}
