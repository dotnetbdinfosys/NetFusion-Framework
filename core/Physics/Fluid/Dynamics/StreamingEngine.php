<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Streaming advection step transferring distributions and handling bounce-back boundaries.
 *
 * @package    NetFusion\Core\Physics\Fluid\Dynamics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Dynamics;

use NetFusion\Core\Physics\Fluid\Enums\BoundaryConditionType;
use NetFusion\Core\Physics\Fluid\Lattice\DiscreteVelocity;
use NetFusion\Core\Physics\Fluid\Lattice\LbmGrid;

class StreamingEngine
{
    /**
     * Stream post-collision distributions across the lattice mesh.
     *
     * @param array<int, array<int, array<int, float>>> $postF Post-collision state
     */
    public function stream(LbmGrid $grid, array $postF): void
    {
        $nx = $grid->getNx();
        $ny = $grid->getNy();
        $nextF = [];

        // Pulling formulation: for each destination node (x, y)
        for ($x = 0; $x < $nx; $x++) {
            $nextF[$x] = [];
            for ($y = 0; $y < $ny; $y++) {
                $node = $grid->getNode($x, $y);
                $nextF[$x][$y] = [];

                if ($node->isSolid()) {
                    continue;
                }

                if ($node->getBoundaryType() === BoundaryConditionType::DIRICHLET_VELOCITY) {
                    $ux = $node->getPrescribedUx();
                    $uy = $node->getPrescribedUy();
                    $nextF[$x][$y] = DiscreteVelocity::allEquilibrium(1.0, $ux, $uy);
                    continue;
                }

                for ($i = 0; $i < 9; $i++) {
                    $cx = DiscreteVelocity::C[$i][0];
                    $cy = DiscreteVelocity::C[$i][1];

                    $srcX = $x - $cx;
                    $srcY = $y - $cy;

                    // Boundary checks
                    if ($srcX < 0 || $srcX >= $nx || $srcY < 0 || $srcY >= $ny) {
                        if ($node->getBoundaryType() === BoundaryConditionType::PERIODIC) {
                            $wrapX = ($srcX + $nx) % $nx;
                            $wrapY = ($srcY + $ny) % $ny;
                            $nextF[$x][$y][$i] = $postF[$wrapX][$wrapY][$i];
                        } else {
                            // Bounce-back from boundary wall
                            $opp = DiscreteVelocity::OPPOSITE[$i];
                            $nextF[$x][$y][$i] = $postF[$x][$y][$opp];
                        }
                    } else {
                        $srcNode = $grid->getNode($srcX, $srcY);
                        if ($srcNode->isSolid()) {
                            // No-slip bounce-back reflection from solid obstacle
                            $opp = DiscreteVelocity::OPPOSITE[$i];
                            $nextF[$x][$y][$i] = $postF[$x][$y][$opp];
                        } else {
                            // Standard advection from fluid neighbor
                            $nextF[$x][$y][$i] = $postF[$srcX][$srcY][$i];
                        }
                    }
                }
            }
        }

        // Apply updated streamed distributions to grid nodes
        for ($x = 0; $x < $nx; $x++) {
            for ($y = 0; $y < $ny; $y++) {
                $node = $grid->getNode($x, $y);
                if (!$node->isSolid() && isset($nextF[$x][$y]) && !empty($nextF[$x][$y])) {
                    $node->setAllF($nextF[$x][$y]);
                }
            }
        }
    }
}
