<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Bhatnagar-Gross-Krook (BGK) relaxation collision engine enforcing stability limits.
 *
 * @package    NetFusion\Core\Physics\Fluid\Dynamics
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Dynamics;

use NetFusion\Core\Physics\Fluid\Enums\CollisionModel;
use NetFusion\Core\Physics\Fluid\Exceptions\UnstableLatticeException;
use NetFusion\Core\Physics\Fluid\Lattice\DiscreteVelocity;
use NetFusion\Core\Physics\Fluid\Lattice\LbmGrid;

class CollisionEngine
{
    /**
     * Relaxation frequency omega = 1 / tau.
     */
    protected float $omega;

    /**
     * Kinematic lattice viscosity nu = (1/3) * (tau - 0.5).
     */
    protected float $viscosity;

    /**
     * @param float $tau Relaxation time parameter (must be > 0.5 for physical stability)
     * @param CollisionModel $model Collision formulation (default BGK)
     */
    public function __construct(
        protected float $tau = 0.6,
        protected readonly CollisionModel $model = CollisionModel::BGK
    ) {
        $this->updateParameters($tau);
    }

    /**
     * Update relaxation time and derived kinematic viscosity.
     */
    public function updateParameters(float $tau): void
    {
        if ($tau <= 0.5) {
            throw new UnstableLatticeException("Relaxation time tau must be strictly > 0.5, got {$tau}.");
        }
        $this->tau = $tau;
        $this->omega = 1.0 / $tau;
        $this->viscosity = DiscreteVelocity::CS2 * ($tau - 0.5);
    }

    public function getTau(): float
    {
        return $this->tau;
    }

    public function getOmega(): float
    {
        return $this->omega;
    }

    public function getViscosity(): float
    {
        return $this->viscosity;
    }

    public function getModel(): CollisionModel
    {
        return $this->model;
    }

    /**
     * Execute collision step across fluid nodes in the grid.
     *
     * Returns post-collision distribution state: postF[x][y][i]
     *
     * @return array<int, array<int, array<int, float>>>
     * @throws UnstableLatticeException
     */
    public function collide(LbmGrid $grid): array
    {
        $nx = $grid->getNx();
        $ny = $grid->getNy();
        $postF = [];
        $omega = $this->omega;

        for ($x = 0; $x < $nx; $x++) {
            $postF[$x] = [];
            for ($y = 0; $y < $ny; $y++) {
                $node = $grid->getNode($x, $y);
                $postF[$x][$y] = [];

                if ($node->isSolid()) {
                    // Solid nodes retain their distribution until bounce-back streaming
                    $postF[$x][$y] = $node->getAllF();
                    continue;
                }

                $rho = $node->getDensity();
                [$ux, $uy] = $node->getVelocity();

                // Stability check: Mach number limit in lattice units (speed < cs ~ 0.577)
                $speed = sqrt($ux * $ux + $uy * $uy);
                if ($speed > 0.5) {
                    throw new UnstableLatticeException(
                        "Lattice velocity blow-up at ({$x}, {$y}): |u| = {$speed} exceeds stability threshold 0.5."
                    );
                }

                $feq = DiscreteVelocity::allEquilibrium($rho, $ux, $uy);

                for ($i = 0; $i < 9; $i++) {
                    $fi = $node->getF($i);
                    // BGK: f_i^* = f_i - omega * (f_i - f_i^(eq))
                    $postF[$x][$y][$i] = $fi - $omega * ($fi - $feq[$i]);
                }
            }
        }

        return $postF;
    }
}
