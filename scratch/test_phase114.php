<?php
/**
 * NetFusion Framework - Phase 114 Test Suite
 *
 * Enterprise Lattice Boltzmann Method (LBM), Computational Fluid Dynamics (CFD), D2Q9 Velocity Grid & Hydrodynamic Fabric.
 *
 * @package    NetFusion\Tests\Phase114
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../core/Support/helpers.php';

// Autoload framework classes manually if no composer autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'NetFusion\\Core\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../core/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use NetFusion\Core\Physics\Fluid\Dynamics\CollisionEngine;
use NetFusion\Core\Physics\Fluid\Dynamics\HydrodynamicField;
use NetFusion\Core\Physics\Fluid\Dynamics\StreamingEngine;
use NetFusion\Core\Physics\Fluid\Enums\BoundaryConditionType;
use NetFusion\Core\Physics\Fluid\Enums\CollisionModel;
use NetFusion\Core\Physics\Fluid\Enums\LatticeModel;
use NetFusion\Core\Physics\Fluid\Exceptions\UnstableLatticeException;
use NetFusion\Core\Physics\Fluid\Lattice\DiscreteVelocity;
use NetFusion\Core\Physics\Fluid\Lattice\LatticeNode;
use NetFusion\Core\Physics\Fluid\Lattice\LbmGrid;
use NetFusion\Core\Physics\Fluid\LbmManager;

$testsPassed = 0;
$totalTests = 10;

function assertTrue(bool $condition, string $msg): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
}

function assertEquals(mixed $expected, mixed $actual, string $msg): void {
    if ($expected !== $actual) {
        throw new RuntimeException("Assertion failed: {$msg}. Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

echo "=== NetFusion Phase 114: Lattice Boltzmann CFD & Hydrodynamics Test Suite ===" . PHP_EOL . PHP_EOL;

// Test 1: LatticeModel and DiscreteVelocity Symmetry
try {
    echo "Running Test 1: D2Q9 Lattice Model, Weights & Direction Symmetry... ";
    $model = LatticeModel::D2Q9;
    assertEquals(2, $model->dimensions(), "D2Q9 dimensions is 2");
    assertEquals(9, $model->directionsCount(), "D2Q9 directions count is 9");

    // Weights sum to 1.0
    $wSum = array_sum(DiscreteVelocity::W);
    assertTrue(abs($wSum - 1.0) < 1e-9, "Weights must sum to 1.0");

    // First moment of weights is zero (isotropic symmetry)
    $sumWx = 0.0;
    $sumWy = 0.0;
    for ($i = 0; $i < 9; $i++) {
        $sumWx += DiscreteVelocity::W[$i] * DiscreteVelocity::C[$i][0];
        $sumWy += DiscreteVelocity::W[$i] * DiscreteVelocity::C[$i][1];

        // Opposite reflection symmetry: opp(opp(i)) === i
        $opp = DiscreteVelocity::OPPOSITE[$i];
        $oppOpp = DiscreteVelocity::OPPOSITE[$opp];
        assertEquals($i, $oppOpp, "Opposite direction of opposite must equal original");
    }
    assertTrue(abs($sumWx) < 1e-9 && abs($sumWy) < 1e-9, "First moment of discrete velocities is zero");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 2: Equilibrium Distribution Conservation Laws
try {
    echo "Running Test 2: Maxwellian Equilibrium Mass & Momentum Conservation... ";
    $rho = 1.25;
    $ux  = 0.05;
    $uy  = -0.02;

    $feq = DiscreteVelocity::allEquilibrium($rho, $ux, $uy);
    assertEquals(9, count($feq), "9 equilibrium components computed");

    // Mass conservation: sum(feq) == rho
    $sumFeq = array_sum($feq);
    assertTrue(abs($sumFeq - $rho) < 1e-9, "Equilibrium must strictly conserve density");

    // Momentum conservation: sum(feq * c) == rho * u
    $momX = 0.0;
    $momY = 0.0;
    for ($i = 0; $i < 9; $i++) {
        $momX += $feq[$i] * DiscreteVelocity::C[$i][0];
        $momY += $feq[$i] * DiscreteVelocity::C[$i][1];
    }
    assertTrue(abs($momX - ($rho * $ux)) < 1e-7, "Equilibrium must conserve X-momentum");
    assertTrue(abs($momY - ($rho * $uy)) < 1e-7, "Equilibrium must conserve Y-momentum");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 3: LatticeNode Hydrodynamic Moments & Solid Masking
try {
    echo "Running Test 3: LatticeNode Microscopic State & Hydrodynamic Moments... ";
    $node = new LatticeNode(5, 10);
    assertEquals(5, $node->getX(), "Node X coordinate");
    assertEquals(10, $node->getY(), "Node Y coordinate");

    // Default equilibrium: rho = 1.0, u = (0, 0)
    assertTrue(abs($node->getDensity() - 1.0) < 1e-9, "Default density is 1.0");
    [$vx, $vy] = $node->getVelocity();
    assertTrue(abs($vx) < 1e-9 && abs($vy) < 1e-9, "Default velocity is 0");

    // Set prescribed moving velocity
    $node->setBoundaryType(BoundaryConditionType::DIRICHLET_VELOCITY);
    $node->setPrescribedVelocity(0.08, 0.0);
    [$vx2, $vy2] = $node->getVelocity();
    assertTrue(abs($vx2 - 0.08) < 1e-9, "Dirichlet prescribed velocity returned");

    // Solid wall node
    $node->setSolid(true);
    assertTrue($node->isSolid(), "Node is solid");
    [$vxSolid, $vySolid] = $node->getVelocity();
    assertEquals([0.0, 0.0], [$vxSolid, $vySolid], "Solid node has zero velocity");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 4: LbmGrid Geometry & Obstacle Placement
try {
    echo "Running Test 4: LbmGrid 2D Mesh & Obstacle Synthesis... ";
    $grid = new LbmGrid(40, 20);
    assertEquals(40, $grid->getNx(), "Grid Nx is 40");
    assertEquals(20, $grid->getNy(), "Grid Ny is 20");

    // Add cylinder obstacle at center (20, 10) with radius 3
    $cylinderNodes = $grid->addCylinder(20, 10, 3.0);
    assertTrue($cylinderNodes > 0, "Cylinder nodes masked as solid");

    $centerNode = $grid->getNode(20, 10);
    assertTrue($centerNode->isSolid(), "Center node is solid");

    // Add rectangular plate at (5, 5) 4x2
    $rectNodes = $grid->addRectObstacle(5, 5, 4, 2);
    assertEquals(8, $rectNodes, "Rectangular obstacle covers 8 nodes");
    assertTrue($grid->getNode(5, 5)->isSolid(), "Rect corner node is solid");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 5: CollisionEngine BGK Relaxation & Viscosity
try {
    echo "Running Test 5: BGK Collision Relaxation & Numerical Viscosity... ";
    $collision = new CollisionEngine(0.65);
    assertEquals(0.65, $collision->getTau(), "Tau parameter is 0.65");

    $expectedNu = (1.0 / 3.0) * (0.65 - 0.5);
    assertTrue(abs($collision->getViscosity() - $expectedNu) < 1e-9, "Viscosity nu = cs^2 * (tau - 0.5)");

    // Stability guardrail: tau <= 0.5 must throw UnstableLatticeException
    $threw = false;
    try {
        new CollisionEngine(0.48);
    } catch (UnstableLatticeException $e) {
        $threw = true;
    }
    assertTrue($threw, "Unstable tau <= 0.5 rejected");

    // Collide on small grid
    $testGrid = new LbmGrid(5, 5);
    $postF = $collision->collide($testGrid);
    assertEquals(5, count($postF), "Post collision state has 5x5 nodes");
    assertEquals(9, count($postF[2][2]), "Center node has 9 distributions");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 6: StreamingEngine Advection & No-Slip Bounce Back
try {
    echo "Running Test 6: Streaming Advection & Halfway Bounce-Back Reflection... ";
    $grid = new LbmGrid(6, 6);
    // Mark (3, 3) as solid obstacle
    $grid->getNode(3, 3)->setSolid(true);

    $collision = new CollisionEngine(0.7);
    $streaming = new StreamingEngine();

    $postF = $collision->collide($grid);
    $streaming->stream($grid, $postF);

    // Node (3, 3) remains solid
    assertTrue($grid->getNode(3, 3)->isSolid(), "Solid obstacle preserved");
    // Neighbor fluid nodes received valid non-nan distributions
    $fluidNeighbor = $grid->getNode(2, 3);
    assertTrue($fluidNeighbor->getDensity() > 0.0, "Fluid neighbor density is positive");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 7: Lid-Driven Cavity Flow Simulation
try {
    echo "Running Test 7: 2D Lid-Driven Cavity Circulation Benchmark... ";
    $grid = new LbmGrid(25, 25);
    $grid->setupLidDrivenCavity(0.08);

    $manager = new LbmManager(25, 25, 0.6, $grid);
    assertEquals(0, $manager->getCurrentStep(), "Initial step is 0");

    // Step 20 iterations
    $manager->step(20);
    assertEquals(20, $manager->getCurrentStep(), "Current step advanced to 20");

    $field = $manager->extractField();
    assertTrue($field->getMaxSpeed() > 0.0, "Max speed in cavity is strictly positive");

    // Moving lid node at (12, 24) has horizontal velocity
    $lidNode = $grid->getNode(12, 24);
    [$lx, $ly] = $lidNode->getVelocity();
    assertTrue($lx > 0.05, "Lid velocity is positive");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 8: HydrodynamicField and Vorticity Curl Computation
try {
    echo "Running Test 8: Hydrodynamic Field & Vorticity Curl Synthesis... ";
    $grid = new LbmGrid(20, 20);
    $grid->setupLidDrivenCavity(0.08);

    $manager = new LbmManager(20, 20, 0.65, $grid);
    $manager->step(15);

    $field = $manager->extractField();
    assertEquals(20, $field->getNx(), "Field Nx matches grid");
    assertEquals(20, $field->getNy(), "Field Ny matches grid");

    // Vorticity calculation
    $hasNonZeroVorticity = false;
    for ($x = 2; $x < 18; $x++) {
        for ($y = 2; $y < 18; $y++) {
            if (abs($field->getVorticity($x, $y)) > 1e-5) {
                $hasNonZeroVorticity = true;
                break 2;
            }
        }
    }
    assertTrue($hasNonZeroVorticity, "Cavity shear flow induces non-zero fluid vorticity");

    $re = $field->getReynoldsNumber(20.0, $manager->getViscosity());
    assertTrue($re > 0.0, "Reynolds number Re must be positive");

    $ascii = $field->renderAscii(20, 8);
    assertTrue(strlen($ascii) > 20, "ASCII heatmap renders successfully");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 9: LbmManager Facade & Performance Benchmark
try {
    echo "Running Test 9: LbmManager Facade & MLUPS Performance Benchmark... ";
    $manager = new LbmManager(20, 20, 0.6);

    $bench = $manager->benchmark(20, 20, 15);
    assertTrue(isset($bench['mlups']), "MLUPS calculated");
    assertTrue($bench['mlups'] > 0.0, "MLUPS throughput is positive");
    assertTrue($bench['elapsed_sec'] > 0.0, "Elapsed time recorded");
    assertEquals(15, $bench['steps'], "Benchmark completed 15 steps");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 10: Global Helpers & CLI Commands
try {
    echo "Running Test 10: Global Helper Functions & CLI Execution... ";
    $mgr = lbm();
    assertTrue($mgr instanceof LbmManager, "lbm() returns LbmManager instance");

    $grid = lbm_grid();
    assertTrue($grid instanceof LbmGrid, "lbm_grid() returns LbmGrid");

    $newGrid = lbm_grid(25, 25);
    assertEquals(25, $newGrid->getNx(), "lbm_grid(25, 25) creates new grid");

    $newGrid->setupLidDrivenCavity(0.06);
    $steps = lbm_step(5);
    assertEquals(5, $steps, "lbm_step(5) stepped 5 iterations");

    $re = lbm_reynolds(25.0);
    assertTrue($re >= 0.0, "lbm_reynolds() returns valid float");

    echo "PASSED" . PHP_EOL;
    $testsPassed++;
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "--------------------------------------------------------" . PHP_EOL;
echo "Phase 114 Test Results: {$testsPassed}/{$totalTests} Passed (" . round(($testsPassed / $totalTests) * 100, 2) . "%)" . PHP_EOL;
echo "--------------------------------------------------------" . PHP_EOL;

if ($testsPassed === $totalTests) {
    echo "SUCCESS: All Phase 114 tests passed!" . PHP_EOL;
    exit(0);
} else {
    echo "FAILURE: Some Phase 114 tests failed." . PHP_EOL;
    exit(1);
}
