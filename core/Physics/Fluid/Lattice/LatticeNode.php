<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Microscopic distribution functions and macroscopic hydrodynamics at a lattice coordinate.
 *
 * @package    NetFusion\Core\Physics\Fluid\Lattice
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid\Lattice;

use NetFusion\Core\Physics\Fluid\Enums\BoundaryConditionType;

class LatticeNode
{
    /**
     * Discrete particle distribution functions f_0..f_8.
     *
     * @var array<int, float>
     */
    protected array $f = [];

    /**
     * @param int $x
     * @param int $y
     * @param bool $isSolid
     * @param BoundaryConditionType $boundaryType
     * @param float $prescribedUx
     * @param float $prescribedUy
     */
    public function __construct(
        protected readonly int $x,
        protected readonly int $y,
        protected bool $isSolid = false,
        protected BoundaryConditionType $boundaryType = BoundaryConditionType::PERIODIC,
        protected float $prescribedUx = 0.0,
        protected float $prescribedUy = 0.0
    ) {
        $this->initializeEquilibrium(1.0, $this->prescribedUx, $this->prescribedUy);
    }

    /**
     * Initialize distribution functions to equilibrium state.
     */
    public function initializeEquilibrium(float $rho = 1.0, float $ux = 0.0, float $uy = 0.0): void
    {
        $this->f = DiscreteVelocity::allEquilibrium($rho, $ux, $uy);
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function isSolid(): bool
    {
        return $this->isSolid;
    }

    public function setSolid(bool $solid, BoundaryConditionType $boundary = BoundaryConditionType::BOUNCE_BACK): void
    {
        $this->isSolid = $solid;
        $this->boundaryType = $boundary;
        if ($solid) {
            $this->prescribedUx = 0.0;
            $this->prescribedUy = 0.0;
        }
    }

    public function getBoundaryType(): BoundaryConditionType
    {
        return $this->boundaryType;
    }

    public function setBoundaryType(BoundaryConditionType $type): void
    {
        $this->boundaryType = $type;
    }

    public function setPrescribedVelocity(float $ux, float $uy): void
    {
        $this->prescribedUx = $ux;
        $this->prescribedUy = $uy;
    }

    public function getPrescribedUx(): float
    {
        return $this->prescribedUx;
    }

    public function getPrescribedUy(): float
    {
        return $this->prescribedUy;
    }

    public function getF(int $i): float
    {
        return $this->f[$i] ?? 0.0;
    }

    public function setF(int $i, float $val): void
    {
        $this->f[$i] = $val;
    }

    /**
     * @return array<int, float>
     */
    public function getAllF(): array
    {
        return $this->f;
    }

    /**
     * @param array<int, float> $f
     */
    public function setAllF(array $f): void
    {
        $this->f = $f;
    }

    /**
     * Calculate macroscopic fluid density: rho = sum(f_i).
     */
    public function getDensity(): float
    {
        return array_sum($this->f);
    }

    /**
     * Calculate macroscopic velocity vector: u = (1/rho) * sum(f_i * c_i).
     *
     * @return array{0: float, 1: float}
     */
    public function getVelocity(): array
    {
        if ($this->isSolid) {
            return [0.0, 0.0];
        }

        if ($this->boundaryType === BoundaryConditionType::DIRICHLET_VELOCITY) {
            return [$this->prescribedUx, $this->prescribedUy];
        }

        $rho = $this->getDensity();
        if ($rho <= 1e-12) {
            return [0.0, 0.0];
        }

        $invRho = 1.0 / $rho;
        $momX = 0.0;
        $momY = 0.0;

        for ($i = 0; $i < 9; $i++) {
            $fi = $this->f[$i];
            $momX += $fi * DiscreteVelocity::C[$i][0];
            $momY += $fi * DiscreteVelocity::C[$i][1];
        }

        return [$momX * $invRho, $momY * $invRho];
    }

    /**
     * Get scalar speed magnitude |u| = sqrt(ux^2 + uy^2).
     */
    public function getSpeed(): float
    {
        [$ux, $uy] = $this->getVelocity();
        return sqrt($ux * $ux + $uy * $uy);
    }
}
