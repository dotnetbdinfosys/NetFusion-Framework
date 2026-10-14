<?php
/**
 * NetFusion Framework - Phase 118 Unit Test Suite
 *
 * Superconductivity, Ginzburg-Landau Theory, Abrikosov Vortices & Josephson SQUID Verification.
 *
 * @package    NetFusion\Tests
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Physics/Superconductivity/Enums/SuperconductorType.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/Enums/SuperconductorMaterial.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/Exceptions/SuperconductivityQuenchException.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/GinzburgLandau/GinzburgLandauEngine.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/Vortex/AbrikosovVortexLattice.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/Josephson/JosephsonJunction.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/Josephson/DCSquidMagnetometer.php';
require_once __DIR__ . '/../core/Physics/Superconductivity/SuperconductivityManager.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorMaterial;
use NetFusion\Core\Physics\Superconductivity\Enums\SuperconductorType;
use NetFusion\Core\Physics\Superconductivity\GinzburgLandau\GinzburgLandauEngine;
use NetFusion\Core\Physics\Superconductivity\Josephson\DCSquidMagnetometer;
use NetFusion\Core\Physics\Superconductivity\Josephson\JosephsonJunction;
use NetFusion\Core\Physics\Superconductivity\SuperconductivityManager;
use NetFusion\Core\Physics\Superconductivity\Vortex\AbrikosovVortexLattice;

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

echo "=== Running Phase 118: Superconductivity & Quantum Magnetics Verification ===\n";

// Test 1: SuperconductorType & SuperconductorMaterial classifications
try {
    $lead = SuperconductorMaterial::LEAD;
    $ybco = SuperconductorMaterial::YBCO;
    $nbti = SuperconductorMaterial::NBTI;

    assertTest(
        $lead->type() === SuperconductorType::TYPE_I &&
        $ybco->type() === SuperconductorType::TYPE_II &&
        $nbti->type() === SuperconductorType::TYPE_II &&
        $ybco->criticalTemperature() === 93.0 &&
        $ybco->isHighTemperature() === true &&
        $nbti->isHighTemperature() === false &&
        $lead->ginzburgLandauKappa() < (1.0 / sqrt(2.0)) &&
        $ybco->ginzburgLandauKappa() > (1.0 / sqrt(2.0)),
        "Test 1: SuperconductorMaterial material benchmarks and Type-I vs Type-II kappa classification"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: GinzburgLandauEngine London penetration depth temperature scaling
try {
    $engine = new GinzburgLandauEngine();
    $mat = SuperconductorMaterial::YBCO; // Tc = 93 K, lambda0 = 150 nm

    $l0   = $engine->getPenetrationDepth($mat, 0.0);
    $l77  = $engine->getPenetrationDepth($mat, 77.0);
    $l93  = $engine->getPenetrationDepth($mat, 93.0); // At Tc -> infinity

    assertTest(
        abs($l0 - 150e-9) < 1e-11 &&
        $l77 > $l0 &&
        !is_finite($l93),
        "Test 2: GinzburgLandauEngine London penetration depth lambda(T) temperature scaling and divergence at Tc"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: GinzburgLandauEngine coherence length temperature scaling
try {
    $engine = new GinzburgLandauEngine();
    $mat = SuperconductorMaterial::YBCO; // Tc = 93 K, xi0 = 1.5 nm

    $xi0  = $engine->getCoherenceLength($mat, 0.0);
    $xi77 = $engine->getCoherenceLength($mat, 77.0);
    $xi93 = $engine->getCoherenceLength($mat, 93.0);

    assertTest(
        abs($xi0 - 1.5e-9) < 1e-11 &&
        $xi77 > $xi0 &&
        $xi93 === 0.0,
        "Test 3: GinzburgLandauEngine coherence length xi(T) temperature scaling"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: Upper and lower critical magnetic fields (Bc1, Bc2)
try {
    $engine = new GinzburgLandauEngine();
    $mat = SuperconductorMaterial::YBCO;

    $bc2_0  = $engine->getUpperCriticalField($mat, 0.0);
    $bc2_77 = $engine->getUpperCriticalField($mat, 77.0);
    $bc1_77 = $engine->getLowerCriticalField($mat, 77.0);

    assertTest(
        $bc2_0 === 120.0 &&
        $bc2_77 > 0.0 &&
        $bc2_77 < $bc2_0 &&
        $bc1_77 > 0.0 &&
        $bc1_77 < $bc2_77,
        "Test 4: Ginzburg-Landau upper critical field Bc2(T) and lower critical field Bc1(T)"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: Ginzburg-Landau depairing critical current density Jc
try {
    $engine = new GinzburgLandauEngine();
    $mat = SuperconductorMaterial::YBCO;

    $jc77 = $engine->getDepairingCurrentDensity($mat, 77.0);

    // Theoretical depairing current density is typically ~ 10^11 to 10^12 A/m^2 (~10^7 to 10^8 A/cm^2)
    assertTest(
        $jc77 > 1e10 &&
        $jc77 < 1e13,
        "Test 5: Ginzburg-Landau depairing critical current density Jc(T) calculation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: Abrikosov flux quantization and triangular vortex lattice spacing
try {
    $lattice = new AbrikosovVortexLattice();
    $fieldT = 1.0; // 1 Tesla applied field

    $density = $lattice->getVortexDensity($fieldT);
    $spacing = $lattice->getLatticeSpacing($fieldT);

    // At 1 Tesla: density nv = 1 / Phi_0 ~ 4.836 x 10^14 m^-2
    // Spacing a_Delta = sqrt(2 * Phi_0 / (sqrt(3) * B)) ~ 48.89 nm
    assertTest(
        abs($density - 4.8359e14) < 1e12 &&
        abs($spacing * 1e9 - 48.89) < 0.50,
        "Test 6: Abrikosov vortex line density nv and triangular lattice spacing a_Delta"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: Vortex pinning vs Lorentz driving force
try {
    $lattice = new AbrikosovVortexLattice();
    // Low current: pinned
    $resPinned = $lattice->evaluateVortexPinning(1.0, 1e8, 1e9);
    // High current: depinned (flux flow)
    $resDepinned = $lattice->evaluateVortexPinning(2.0, 1e10, 1e9);

    assertTest(
        $resPinned['is_pinned'] === true &&
        $resDepinned['is_pinned'] === false &&
        $resPinned['lorentz_force_n_m3'] < $resPinned['pinning_force_n_m3'] &&
        $resDepinned['lorentz_force_n_m3'] > $resDepinned['pinning_force_n_m3'],
        "Test 7: Vortex pinning Lorentz force balance FL = J x B and depinning transition"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: Josephson Junction DC supercurrent and AC frequency
try {
    $junction = new JosephsonJunction('jj1', 10e-6, 5.0, 1e-12);

    $isMax = $junction->getSupercurrent(M_PI / 2.0); // Phase pi/2 -> Is = Ic
    $isZero = $junction->getSupercurrent(0.0);
    $fAc = $junction->getJosephsonFrequency(1e-3); // 1 mV voltage -> ~483.6 GHz
    $vOverdamped = $junction->computeOverdampedVoltage(20e-6); // I = 2*Ic > Ic

    assertTest(
        abs($isMax - 10e-6) < 1e-10 &&
        abs($isZero) < 1e-12 &&
        abs($fAc / 1e9 - 483.6) < 1.0 &&
        $junction->isOverdamped() === true &&
        $vOverdamped > 0.0,
        "Test 8: JosephsonJunction DC supercurrent phase relation and AC Josephson frequency"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: DC-SQUID macroscopic quantum interference and voltage modulation
try {
    $squid = new DCSquidMagnetometer(1e-8, 100e-12);

    // Applied field matching integer flux quantum Phi = Phi_0
    $field0 = 0.0;
    $fieldPhi0 = GinzburgLandauEngine::FLUX_QUANTUM / 1e-8;
    $fieldHalfPhi0 = (0.5 * GinzburgLandauEngine::FLUX_QUANTUM) / 1e-8;

    $ic0 = $squid->getMaximumSupercurrent($squid->computeMagneticFlux($field0));
    $icHalf = $squid->getMaximumSupercurrent($squid->computeMagneticFlux($fieldHalfPhi0));
    $icFull = $squid->getMaximumSupercurrent($squid->computeMagneticFlux($fieldPhi0));

    assertTest(
        abs($ic0 - 20e-6) < 1e-9 &&
        abs($icHalf) < 1e-10 && // At Half Phi_0, destructive interference -> Ic ~ 0
        abs($icFull - 20e-6) < 1e-9 && // Periodic modulation returns to maximum
        $squid->getScreeningParameter() > 0.0,
        "Test 9: DC-SQUID macroscopic quantum interference, destructive interference, and Phi_0 periodicity"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: SuperconductivityManager master facade and global helpers
try {
    $mgr = supercond();
    $state = supercond_gl(SuperconductorMaterial::YBCO, 77.0, 1.0);
    $vortex = supercond_vortex(1.0);
    $squid = supercond_squid();

    assertTest(
        $mgr instanceof SuperconductivityManager &&
        $state['is_superconducting'] === true &&
        $state['state_regime'] === 'ABRIKOSOV_MIXED_VORTEX_STATE' &&
        isset($vortex['vortex_density_m2']) &&
        $squid instanceof DCSquidMagnetometer &&
        function_exists('supercond') && function_exists('supercond_gl') &&
        function_exists('supercond_vortex') && function_exists('supercond_squid'),
        "Test 10: SuperconductivityManager master facade and global helper functions"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

echo "=== Phase 118 Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
