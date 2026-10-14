<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * Master orchestrator facade managing grid lifecycle, collision-streaming timestepping, and telemetry.
 *
 * @package    NetFusion\Core\Physics\Fluid
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Physics\Fluid;

use NetFusion\Core\Physics\Fluid\Dynamics\CollisionEngine;
use NetFusion\Core\Physics\Fluid\Dynamics\HydrodynamicField;
use NetFusion\Core\Physics\Fluid\Dynamics\StreamingEngine;
use NetFusion\Core\Physics\Fluid\Enums\CollisionModel;
use NetFusion\Core\Physics\Fluid\Enums\LatticeModel;
use NetFusion\Core\Physics\Fluid\Lattice\LbmGrid;

class LbmManager
{
    protected LbmGrid $grid;
    protected CollisionEngine $collisionEngine;
    protected StreamingEngine $streamingEngine;
    protected int $currentStep = 0;

    public function __construct(
        int $defaultNx = 40,
        int $defaultNy = 40,
        float $tau = 0.6,
        ?LbmGrid $grid = null,
        ?CollisionEngine $collisionEngine = null,
        ?StreamingEngine $streamingEngine = null
    ) {
        $this->grid            = $grid ?? new LbmGrid($defaultNx, $defaultNy, LatticeModel::D2Q9);
        $this->collisionEngine = $collisionEngine ?? new CollisionEngine($tau, CollisionModel::BGK);
        $this->streamingEngine = $streamingEngine ?? new StreamingEngine();
    }

    /**
     * Create and initialize a new grid mesh.
     */
    public function createGrid(int $nx, int $ny, LatticeModel $model = LatticeModel::D2Q9): LbmGrid
    {
        $this->grid = new LbmGrid($nx, $ny, $model);
        $this->currentStep = 0;
        return $this->grid;
    }

    public function getGrid(): LbmGrid
    {
        return $this->grid;
    }

    public function setGrid(LbmGrid $grid): void
    {
        $this->grid = $grid;
        $this->currentStep = 0;
    }

    public function getCollisionEngine(): CollisionEngine
    {
        return $this->collisionEngine;
    }

    public function getStreamingEngine(): StreamingEngine
    {
        return $this->streamingEngine;
    }

    public function setTau(float $tau): void
    {
        $this->collisionEngine->updateParameters($tau);
    }

    public function getTau(): float
    {
        return $this->collisionEngine->getTau();
    }

    public function getViscosity(): float
    {
        return $this->collisionEngine->getViscosity();
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    /**
     * Advance the simulation by N discrete time steps (Collision -> Streaming cycle).
     */
    public function step(int $iterations = 1): int
    {
        for ($i = 0; $i < $iterations; $i++) {
            $postF = $this->collisionEngine->collide($this->grid);
            $this->streamingEngine->stream($this->grid, $postF);
            $this->currentStep++;
        }

        return $this->currentStep;
    }

    /**
     * Extract macroscopic density, velocity, and vorticity fields.
     */
    public function extractField(): HydrodynamicField
    {
        return HydrodynamicField::fromGrid($this->grid);
    }

    /**
     * Calculate global Reynolds number for current hydrodynamic state.
     */
    public function getReynoldsNumber(float $charLength = 10.0): float
    {
        $field = $this->extractField();
        return $field->getReynoldsNumber($charLength, $this->getViscosity());
    }

    /**
     * Benchmark performance in Million Lattice Updates Per Second (MLUPS).
     *
     * @return array{nx: int, ny: int, steps: int, elapsed_sec: float, mlups: float}
     */
    public function benchmark(int $nx = 40, int $ny = 40, int $steps = 25): array
    {
        $testGrid = new LbmGrid($nx, $ny, LatticeModel::D2Q9);
        $testGrid->setupLidDrivenCavity(0.08);

        $prevGrid = $this->grid;
        $this->grid = $testGrid;

        $t0 = microtime(true);
        $this->step($steps);
        $elapsed = max(1e-6, microtime(true) - $t0);

        $totalUpdates = $nx * $ny * $steps;
        $mlups = ($totalUpdates / $elapsed) / 1_000_000.0;

        $this->grid = $prevGrid;

        return [
            'nx'          => $nx,
            'ny'          => $ny,
            'steps'       => $steps,
            'elapsed_sec' => round($elapsed, 4),
            'mlups'       => round($mlups, 4),
        ];
    }
}
