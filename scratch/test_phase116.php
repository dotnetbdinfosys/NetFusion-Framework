<?php
/**
 * NetFusion Framework - Phase 116 Unit Test Suite
 *
 * Astro-Informatics, Symplectic N-Body Integrators & Orbital Mechanics Verification.
 *
 * @package    NetFusion\Tests
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Astro/Enums/CelestialBodyType.php';
require_once __DIR__ . '/../core/Astro/Enums/IntegratorType.php';
require_once __DIR__ . '/../core/Astro/Exceptions/AstroPhysicsException.php';
require_once __DIR__ . '/../core/Astro/Exceptions/KeplerConvergenceException.php';
require_once __DIR__ . '/../core/Astro/Orbit/KeplerianElements.php';
require_once __DIR__ . '/../core/Astro/Orbit/OrbitalMechanics.php';
require_once __DIR__ . '/../core/Astro/Body/CelestialBody.php';
require_once __DIR__ . '/../core/Astro/Integrator/SymplecticNBodyIntegrator.php';
require_once __DIR__ . '/../core/Astro/AstroManager.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Astro\AstroManager;
use NetFusion\Core\Astro\Body\CelestialBody;
use NetFusion\Core\Astro\Enums\CelestialBodyType;
use NetFusion\Core\Astro\Enums\IntegratorType;
use NetFusion\Core\Astro\Exceptions\KeplerConvergenceException;
use NetFusion\Core\Astro\Integrator\SymplecticNBodyIntegrator;
use NetFusion\Core\Astro\Orbit\KeplerianElements;
use NetFusion\Core\Astro\Orbit\OrbitalMechanics;

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

echo "=== Running Phase 116: Astro-Informatics & Celestial Mechanics Verification ===\n";

// Test 1: CelestialBodyType and IntegratorType enums
try {
    $star = CelestialBodyType::STAR;
    $planet = CelestialBodyType::PLANET;
    $leapfrog = IntegratorType::LEAPFROG;
    $yoshida = IntegratorType::YOSHIDA_4TH;
    $rk4 = IntegratorType::RK4;

    assertTest(
        $star->isMajorGravitationalSource() === true &&
        $planet->isMajorGravitationalSource() === true &&
        $leapfrog->isSymplectic() === true &&
        $leapfrog->order() === 2 &&
        $yoshida->isSymplectic() === true &&
        $yoshida->order() === 4 &&
        $rk4->isSymplectic() === false &&
        $rk4->order() === 4,
        "Test 1: CelestialBodyType and IntegratorType specifications, symplectic classification"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: KeplerianElements properties, period & energy
try {
    $a = 1.496e11; // 1 AU
    $e = 0.0167;   // Earth eccentricity
    $elements = new KeplerianElements($a, $e, 0.0, 0.0, 0.0, 0.0, KeplerianElements::MU_SUN);

    $periapsis = $elements->getPeriapsis();
    $apoapsis  = $elements->getApoapsis();
    $period    = $elements->getPeriod() / 86400.0; // days
    $energy    = $elements->getSpecificEnergy();

    assertTest(
        $periapsis < $a &&
        $apoapsis > $a &&
        abs($period - 365.25) < 3.0 && // Earth orbital period ~ 365.25 days
        $energy < 0.0, // Bound elliptical orbit has negative specific orbital energy
        "Test 2: KeplerianElements periapsis, apoapsis, orbital period and specific orbital energy"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: OrbitalMechanics Kepler equation solver
try {
    $mechanics = new OrbitalMechanics();
    // Test case: M = 1.0 rad, e = 0.5
    $m = 1.0;
    $e = 0.5;
    $eVal = $mechanics->solveKepler($m, $e);

    // Verify Kepler equation residual: |E - e*sin(E) - M| < 1e-9
    $residual = abs($eVal - ($e * sin($eVal)) - $m);

    $nu = $mechanics->eccentricToTrueAnomaly($eVal, $e);

    assertTest(
        $residual < 1e-9 &&
        $nu > 0.0 &&
        $nu < 2.0 * M_PI,
        "Test 3: OrbitalMechanics Newton-Raphson Kepler solver and true anomaly calculation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: Keplerian Elements to 3D Cartesian state vector conversion
try {
    $mechanics = new OrbitalMechanics();
    $a = 1.0 * KeplerianElements::AU_METERS;
    $e = 0.0; // Circular orbit
    $elements = new KeplerianElements($a, $e, 0.0, 0.0, 0.0, 0.0, KeplerianElements::MU_SUN);

    $cartesian = $mechanics->elementsToCartesian($elements);
    $pos = $cartesian['position'];
    $vel = $cartesian['velocity'];

    $rMag = sqrt(($pos['x'] ** 2) + ($pos['y'] ** 2) + ($pos['z'] ** 2));
    $vMag = sqrt(($vel['x'] ** 2) + ($vel['y'] ** 2) + ($vel['z'] ** 2));

    // Expected circular orbital speed v = sqrt(mu / r) ~ 29.78 km/s
    $vExpected = sqrt(KeplerianElements::MU_SUN / $a);

    assertTest(
        abs($rMag - $a) < 1.0 && // Radius matches semi-major axis exactly
        abs($vMag - $vExpected) < 10.0 && // Speed matches ~ 29.78 km/s
        abs($pos['z']) < 1e-6 && abs($vel['z']) < 1e-6, // Coplanar z = 0
        "Test 4: Keplerian elements to 3D Cartesian coordinates conversion"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: Hohmann interplanetary transfer calculations
try {
    $mechanics = new OrbitalMechanics();
    $r1 = 1.0 * KeplerianElements::AU_METERS;   // Earth orbit
    $r2 = 1.524 * KeplerianElements::AU_METERS; // Mars orbit

    $hohmann = $mechanics->computeHohmannTransfer($r1, $r2, KeplerianElements::MU_SUN);
    $dvTotalKm = $hohmann['total_delta_v'] / 1000.0;
    $tofDays   = $hohmann['transfer_time_seconds'] / 86400.0;

    // Classic Earth-Mars Hohmann: total delta-v ~ 5.59 km/s, TOF ~ 258 days
    assertTest(
        abs($dvTotalKm - 5.59) < 0.20 &&
        abs($tofDays - 258.0) < 5.0 &&
        $hohmann['delta_v1'] > 0.0 &&
        $hohmann['delta_v2'] > 0.0,
        "Test 5: Hohmann interplanetary transfer delta-v and time of flight"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: Circular restricted three-body Lagrange points
try {
    $mechanics = new OrbitalMechanics();
    // Sun-Earth system: M1 = 1.989e30, M2 = 5.972e24, distance = 1 AU
    $mSun = 1.989e30;
    $mEarth = 5.972e24;
    $rAu = 1.0;

    $lagrange = $mechanics->computeLagrangePoints($mSun, $mEarth, $rAu);

    assertTest(
        isset($lagrange['L1'], $lagrange['L2'], $lagrange['L3'], $lagrange['L4'], $lagrange['L5']) &&
        $lagrange['L1']['x'] < 1.0 && // L1 is sunward of Earth
        $lagrange['L2']['x'] > 1.0 && // L2 is anti-sunward of Earth
        $lagrange['L4']['y'] > 0.0 && // L4 is leading Trojan
        $lagrange['L5']['y'] < 0.0,   // L5 is trailing Trojan
        "Test 6: Collinear and triangular Lagrange equilibrium points (L1..L5)"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: CelestialBody entity dynamics & energy
try {
    $earth = new CelestialBody(
        'earth',
        'Earth',
        CelestialBodyType::PLANET,
        5.972e24,
        6.371e6,
        ['x' => 1.496e11, 'y' => 0.0, 'z' => 0.0],
        ['x' => 0.0, 'y' => 29780.0, 'z' => 0.0]
    );

    $speed = $earth->speed();
    $ke = $earth->kineticEnergy();
    $arr = $earth->toArray();

    assertTest(
        abs($speed - 29780.0) < 1.0 &&
        $ke > 0.0 &&
        $arr['body_id'] === 'earth' &&
        $arr['type'] === 'planet',
        "Test 7: CelestialBody kinetic energy, velocity magnitude, and serialization"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: Symplectic 2nd-order Leapfrog integrator energy conservation
try {
    $integrator = new SymplecticNBodyIntegrator(1e3);

    // Two-body system: Star and Planet
    $star = new CelestialBody(
        'star', 'Star', CelestialBodyType::STAR,
        1.989e30, 6.96e8,
        ['x' => 0.0, 'y' => 0.0, 'z' => 0.0],
        ['x' => 0.0, 'y' => 0.0, 'z' => 0.0]
    );
    $planet = new CelestialBody(
        'planet', 'Planet', CelestialBodyType::PLANET,
        5.972e24, 6.37e6,
        ['x' => 1.496e11, 'y' => 0.0, 'z' => 0.0],
        ['x' => 0.0, 'y' => 29780.0, 'z' => 0.0]
    );

    $e0 = $integrator->computeMechanicalEnergy([$star, $planet])['total'];

    // Step 50 times with dt = 86400s (50 days)
    for ($s = 0; $s < 50; $s++) {
        $integrator->stepLeapfrog([$star, $planet], 86400.0);
    }

    $e50 = $integrator->computeMechanicalEnergy([$star, $planet])['total'];
    $driftPct = abs(($e50 - $e0) / $e0) * 100.0;

    assertTest(
        $driftPct < 0.05, // Symplectic drift is less than 0.05% after 50 days
        "Test 8: Symplectic 2nd-order Leapfrog Hamiltonian energy conservation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: Symplectic 4th-order Yoshida integrator
try {
    $integrator = new SymplecticNBodyIntegrator(1e3);

    $star = new CelestialBody(
        'star', 'Star', CelestialBodyType::STAR,
        1.989e30, 6.96e8,
        ['x' => 0.0, 'y' => 0.0, 'z' => 0.0],
        ['x' => 0.0, 'y' => 0.0, 'z' => 0.0]
    );
    $planet = new CelestialBody(
        'planet', 'Planet', CelestialBodyType::PLANET,
        5.972e24, 6.37e6,
        ['x' => 1.496e11, 'y' => 0.0, 'z' => 0.0],
        ['x' => 0.0, 'y' => 29780.0, 'z' => 0.0]
    );

    $e0 = $integrator->computeMechanicalEnergy([$star, $planet])['total'];

    // 20 steps of 4th-order Yoshida
    for ($s = 0; $s < 20; $s++) {
        $integrator->stepYoshida4th([$star, $planet], 86400.0);
    }

    $e20 = $integrator->computeMechanicalEnergy([$star, $planet])['total'];
    $driftPct = abs(($e20 - $e0) / $e0) * 100.0;

    assertTest(
        $driftPct < 0.01, // 4th order symplectic integrator provides tighter energy conservation
        "Test 9: 4th-Order Yoshida symplectic composition integrator accuracy"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: AstroManager and global helpers
try {
    $mgr = astro();
    $mgr->loadMiniSolarSystem();
    $history = $mgr->propagate(5, 86400.0, IntegratorType::LEAPFROG);

    $keplerRes = astro_kepler(1.496e11, 0.0167);
    $hohmannRes = astro_hohmann(1.496e11, 1.524 * 1.496e11);

    assertTest(
        $mgr instanceof AstroManager &&
        count($mgr->getBodies()) === 3 &&
        count($history) === 6 &&
        isset($keplerRes['position'], $keplerRes['velocity']) &&
        isset($hohmannRes['total_delta_v']) &&
        function_exists('astro') && function_exists('astro_kepler') &&
        function_exists('astro_nbody') && function_exists('astro_hohmann'),
        "Test 10: AstroManager orchestration and global helper functions"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

echo "=== Phase 116 Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
