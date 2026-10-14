<?php
/**
 * NetFusion Framework - Phase 117 Unit Test Suite
 *
 * Plasma Physics, Tokamak Confinement, MHD Equilibrium & Fusion Kinetics Verification.
 *
 * @package    NetFusion\Tests
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Physics/Plasma/Enums/FuelMixture.php';
require_once __DIR__ . '/../core/Physics/Plasma/Enums/InstabilityMode.php';
require_once __DIR__ . '/../core/Physics/Plasma/Exceptions/PlasmaInstabilityException.php';
require_once __DIR__ . '/../core/Physics/Plasma/Tokamak/ToroidalField.php';
require_once __DIR__ . '/../core/Physics/Plasma/Mhd/GradShafranovSolver.php';
require_once __DIR__ . '/../core/Physics/Plasma/Kinetics/FusionCrossSection.php';
require_once __DIR__ . '/../core/Physics/Plasma/Kinetics/LawsonEvaluator.php';
require_once __DIR__ . '/../core/Physics/Plasma/PlasmaManager.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Physics\Plasma\Enums\FuelMixture;
use NetFusion\Core\Physics\Plasma\Enums\InstabilityMode;
use NetFusion\Core\Physics\Plasma\Kinetics\FusionCrossSection;
use NetFusion\Core\Physics\Plasma\Kinetics\LawsonEvaluator;
use NetFusion\Core\Physics\Plasma\Mhd\GradShafranovSolver;
use NetFusion\Core\Physics\Plasma\PlasmaManager;
use NetFusion\Core\Physics\Plasma\Tokamak\ToroidalField;

$testsPassed = 0;
$totalTests  = 10;

function assertTest(bool $condition, string $testName): void {
    global $testsPassed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$testName}\n";
    }
}

echo "=== Running Phase 117: Plasma Physics & Fusion Reactor Verification ===\n";

// Test 1: FuelMixture specifications & reaction energies
try {
    $dt = FuelMixture::D_T;
    $pb = FuelMixture::P_B11;

    assertTest(
        $dt->reactionEnergyMeV() === 17.59 &&
        abs($dt->chargedFraction() - 0.20) < 0.01 &&
        $dt->isAneutronic() === false &&
        $pb->isAneutronic() === true &&
        $pb->optimalTemperatureKeV() === 200.0,
        "Test 1: FuelMixture reaction energies, charged fractions, and aneutronic classifications"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: InstabilityMode classifications
try {
    $kink = InstabilityMode::KINK;
    $sausage = InstabilityMode::SAUSAGE;
    $ballooning = InstabilityMode::BALLOONING;

    assertTest(
        $kink->isMhdDisruptive() === true &&
        $kink->poloidalModeNumber() === 1 &&
        $sausage->poloidalModeNumber() === 0 &&
        $ballooning->isMhdDisruptive() === true,
        "Test 2: InstabilityMode poloidal mode numbers and disruptive classifications"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: ToroidalField geometry, volume, and safety factor q-profile
try {
    $tokamak = new ToroidalField(6.2, 2.0, 5.3, 15e6, 1.7);

    $aspect = $tokamak->getAspectRatio();
    $vol = $tokamak->getPlasmaVolume();
    $qEdge = $tokamak->getEdgeSafetyFactor();
    $q0 = $tokamak->getSafetyFactorAt(0.0);

    assertTest(
        abs($aspect - 3.1) < 0.05 &&
        $vol > 800.0 && // ITER plasma volume ~ 840 m^3
        $qEdge > 2.0 &&
        $q0 < $qEdge,
        "Test 3: ToroidalField major/minor dimensions, plasma volume, and parabolic q-profile"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: Kruskal-Shafranov stability criterion
try {
    $stableTokamak = new ToroidalField(6.2, 2.0, 5.3, 15e6);
    // Artificially excessive current (80 MA) drops q_edge < 1.0 (external kink disruption)
    $unstableTokamak = new ToroidalField(6.2, 2.0, 5.3, 80e6);

    assertTest(
        $stableTokamak->isKruskalShafranovStable(1.0) === true &&
        $unstableTokamak->isKruskalShafranovStable(1.0) === false &&
        $unstableTokamak->getEdgeSafetyFactor() < 1.0,
        "Test 4: ToroidalField Kruskal-Shafranov limit verification and kink instability detection"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: GradShafranovSolver 2D equilibrium and convergence
try {
    $tokamak = new ToroidalField(6.2, 2.0, 5.3, 15e6);
    $solver = new GradShafranovSolver();
    $eq = $solver->solveEquilibrium($tokamak, 15, 15, 1.5e5, 100);

    assertTest(
        isset($eq['psi'], $eq['beta_toroidal'], $eq['beta_poloidal']) &&
        $eq['beta_toroidal'] > 0.0 &&
        $eq['beta_toroidal'] < 0.10 &&
        count($eq['psi']) === 15 &&
        count($eq['psi'][0]) === 15,
        "Test 5: GradShafranovSolver 2D finite-difference SOR equilibrium flux psi(R,Z)"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: Troyon beta operational limit
try {
    $tokamak = new ToroidalField(6.2, 2.0, 5.3, 15e6);
    $solver = new GradShafranovSolver();
    $eq = $solver->solveEquilibrium($tokamak, 10, 10, 1.5e5, 50);

    // Troyon beta limit ~ 2.8 * (15 / (2.0 * 5.3)) % ~ 3.96%
    $troyon = $eq['troyon_limit'] * 100.0;

    assertTest(
        abs($troyon - 3.96) < 0.20 &&
        $eq['beta_toroidal'] < $eq['troyon_limit'],
        "Test 6: GradShafranovSolver Troyon normalized beta scaling and ballooning operational margins"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: FusionCrossSection Maxwellian reaction rates
try {
    $cross = new FusionCrossSection();
    $sv10 = $cross->getReactivity(10.0, FuelMixture::D_T);
    $sv15 = $cross->getReactivity(15.0, FuelMixture::D_T);
    $sv20 = $cross->getReactivity(20.0, FuelMixture::D_T);

    assertTest(
        $sv10 > 0.0 &&
        $sv15 > $sv10 &&
        $sv20 > $sv15 &&
        $sv15 > 1e-22 && $sv15 < 5e-22, // Standard D-T reactivity range at 15 keV ~ 2.5e-22 m^3/s
        "Test 7: FusionCrossSection Bosch-Hale parameterized D-T thermonuclear reactivity"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: Fusion volumetric power density and Bremsstrahlung radiation
try {
    $cross = new FusionCrossSection();
    $pDens = $cross->computePowerDensity(1e20, 15.0, FuelMixture::D_T);

    assertTest(
        $pDens['total_power_density_w'] > 0.0 &&
        $pDens['charged_power_density_w'] > 0.0 &&
        $pDens['bremsstrahlung_loss_w'] > 0.0 &&
        $pDens['charged_power_density_w'] > $pDens['bremsstrahlung_loss_w'],
        "Test 8: Volumetric fusion power density, alpha self-heating, and Bremsstrahlung radiation loss"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: LawsonEvaluator triple product and Q-factor energy gain
try {
    $eval = new LawsonEvaluator();
    // ITER parameters: V = 840 m^3, n = 1e20 m^-3, T = 15 keV, tau = 3.5s, P_aux = 50 MW
    $res = $eval->evaluatePowerBalance(840.0, 1e20, 15.0, 3.5, 50.0, FuelMixture::D_T);

    assertTest(
        $res['triple_product'] >= LawsonEvaluator::DT_IGNITION_TRIPLE_PRODUCT * 0.1 &&
        $res['fusion_power_mw'] > 100.0 && // Fusion power in hundreds of MW
        $res['q_factor'] > 5.0 &&          // ITER target Q ~ 10
        $res['is_breakeven'] === true,
        "Test 9: LawsonEvaluator triple product, thermonuclear Q-gain factor, and breakeven condition"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: PlasmaManager facade and global helpers
try {
    $mgr = plasma();
    $tok = plasma_tokamak();
    $sv = plasma_cross_section(15.0);
    $lawson = plasma_lawson(1.0, 15.0, 3.5, 50.0);

    assertTest(
        $mgr instanceof PlasmaManager &&
        $tok instanceof ToroidalField &&
        $sv > 0.0 &&
        isset($lawson['q_factor']) &&
        function_exists('plasma') && function_exists('plasma_tokamak') &&
        function_exists('plasma_cross_section') && function_exists('plasma_lawson'),
        "Test 10: PlasmaManager master facade and global helper functions"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

echo "=== Phase 117 Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
