<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Fabric
 *
 * 2D Lattice mesh representation with boundary geometry and obstacle mask synthesis.
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
use NetFusion\Core\Physics\Fluid\Enums\LatticeModel;

class LbmGrid
{
    /**
     * 2D Grid node matrix: [x][y] => LatticeNode.
     *
     * @var array<int, array<int, LatticeNode>>
     */
    protected array $nodes = [];

    public function __construct(
        protected readonly int $nx,
        protected readonly int $ny,
        protected readonly LatticeModel $model = LatticeModel::D2Q9
    ) {
        $this->initializeGrid();
    }

    /**
     * Initialize grid mesh with fluid nodes.
     */
    protected function initializeGrid(): void
    {
        $this->nodes = [];
        for ($x = 0; $x < $this->nx; $x++) {
            $this->nodes[$x] = [];
            for ($y = 0; $y < $this->ny; $y++) {
                $this->nodes[$x][$y] = new LatticeNode($x, $y);
            }
        }
    }

    public function getNx(): int
    {
        return $this->nx;
    }

    public function getNy(): int
    {
        return $this->ny;
    }

    public function getModel(): LatticeModel
    {
        return $this->model;
    }

    public function getNode(int $x, int $y): ?LatticeNode
    {
        return $this->nodes[$x][$y] ?? null;
    }

    /**
     * @return array<int, array<int, LatticeNode>>
     */
    public function getAllNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Mask solid obstacle by user-supplied spatial predicate (fn(x, y): bool).
     */
    public function addSolidObstacle(callable $predicate): int
    {
        $count = 0;
        for ($x = 0; $x < $this->nx; $x++) {
            for ($y = 0; $y < $this->ny; $y++) {
                if ($predicate($x, $y)) {
                    $this->nodes[$x][$y]->setSolid(true, BoundaryConditionType::BOUNCE_BACK);
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Place circular obstacle (cylinder in crossflow).
     */
    public function addCylinder(int $centerX, int $centerY, float $radius): int
    {
        $r2 = $radius * $radius;
        return $this->addSolidObstacle(function (int $x, int $y) use ($centerX, $centerY, $r2) {
            $dx = $x - $centerX;
            $dy = $y - $centerY;
            return ($dx * $dx + $dy * $dy) <= $r2;
        });
    }

    /**
     * Place rectangular solid block / plate.
     */
    public function addRectObstacle(int $x0, int $y0, int $width, int $height): int
    {
        return $this->addSolidObstacle(function (int $x, int $y) use ($x0, $y0, $width, $height) {
            return ($x >= $x0 && $x < $x0 + $width && $y >= $y0 && $y < $y0 + $height);
        });
    }

    /**
     * Configure canonical Lid-Driven Cavity Flow testbench:
     * - Bottom (y=0), Left (x=0), Right (x=nx-1) solid walls with bounce-back.
     * - Top lid (y=ny-1) moving horizontally with prescribed speed.
     */
    public function setupLidDrivenCavity(float $lidUx = 0.1): void
    {
        for ($x = 0; $x < $this->nx; $x++) {
            for ($y = 0; $y < $this->ny; $y++) {
                if ($y === $this->ny - 1) {
                    // Moving lid (Dirichlet velocity)
                    $node = $this->nodes[$x][$y];
                    $node->setBoundaryType(BoundaryConditionType::DIRICHLET_VELOCITY);
                    $node->setPrescribedVelocity($lidUx, 0.0);
                    $node->initializeEquilibrium(1.0, $lidUx, 0.0);
                } elseif ($y === 0 || $x === 0 || $x === $this->nx - 1) {
                    // Stationary solid walls
                    $this->nodes[$x][$y]->setSolid(true, BoundaryConditionType::BOUNCE_BACK);
                }
            }
        }
    }

    /**
     * Configure planar channel with solid top and bottom walls.
     */
    public function setupChannel(float $inletUx = 0.05): void
    {
        for ($x = 0; $x < $this->nx; $x++) {
            // Bottom and top stationary walls
            $this->nodes[$x][0]->setSolid(true, BoundaryConditionType::BOUNCE_BACK);
            $this->nodes[$x][$this->ny - 1]->setSolid(true, BoundaryConditionType::BOUNCE_BACK);
        }

        // Initialize uniform horizontal fluid velocity inside
        for ($x = 0; $x < $this->nx; $x++) {
            for ($y = 1; $y < $this->ny - 1; $y++) {
                if (!$this->nodes[$x][$y]->isSolid()) {
                    $this->nodes[$x][$y]->initializeEquilibrium(1.0, $inletUx, 0.0);
                }
            }
        }
    }
}
