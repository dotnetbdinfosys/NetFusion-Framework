<?php
/**
 * NetFusion Framework - Phase 119 Verification Suite
 *
 * Quantum Thermodynamics, Open Quantum Systems (Lindblad Master Equation) & Quantum Heat Engines Fabric.
 *
 * @package NetFusion\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/Enums/ThermalCycleType.php';
require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/Enums/DissipationChannel.php';
require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/Exceptions/QuantumThermodynamicsException.php';
require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/State/DensityMatrix.php';
require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/OpenSystem/LindbladSolver.php';
require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/Engine/QuantumHeatEngine.php';
require_once __DIR__ . '/../core/Physics/QuantumThermodynamics/QuantumThermodynamicsManager.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Physics\QuantumThermodynamics\Enums\ThermalCycleType;
use NetFusion\Core\Physics\QuantumThermodynamics\Enums\DissipationChannel;
use NetFusion\Core\Physics\QuantumThermodynamics\State\DensityMatrix;
use NetFusion\Core\Physics\QuantumThermodynamics\OpenSystem\LindbladSolver;
use NetFusion\Core\Physics\QuantumThermodynamics\Engine\QuantumHeatEngine;
use NetFusion\Core\Physics\QuantumThermodynamics\QuantumThermodynamicsManager;

$testsPassed = 0;
$totalTests = 10;

function assertTest(bool $condition, string $message): void {
    global $testsPassed;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

echo "=== Running Phase 119: Quantum Thermodynamics Verification ===\n";

// Test 1: Enums validation
try {
    $otto = ThermalCycleType::OTTO;
    $carnot = ThermalCycleType::CARNOT;
    $stirling = ThermalCycleType::STIRLING;
    $damp = DissipationChannel::AMPLITUDE_DAMPING;
    $dephase = DissipationChannel::DEPHASING;
    $pump = DissipationChannel::THERMAL_PUMPING;

    assertTest(
        $otto->value === 'otto' &&
        $carnot->value === 'carnot' &&
        $stirling->value === 'stirling' &&
        $damp->value === 'amplitude_damping' &&
        $dephase->value === 'dephasing' &&
        $pump->value === 'thermal_pumping' &&
        str_contains($otto->efficiencyFormula(), 'omega_c') &&
        str_contains($damp->jumpOperatorLabel(), 'sigma_-'),
        "Test 1: ThermalCycleType and DissipationChannel enums and properties"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: DensityMatrix pure states, trace normalization, and purity
try {
    $ground = DensityMatrix::groundState();
    $excited = DensityMatrix::excitedState();
    $mixed = DensityMatrix::maximumMixed();

    assertTest(
        abs($ground->trace() - 1.0) < 1e-12 &&
        abs($ground->purity() - 1.0) < 1e-12 &&
        $ground->isPure() === true &&
        $ground->rho00() === 1.0 &&
        $ground->rho11() === 0.0 &&
        abs($excited->purity() - 1.0) < 1e-12 &&
        $excited->rho11() === 1.0 &&
        abs($mixed->purity() - 0.5) < 1e-12 &&
        $mixed->isPure() === false,
        "Test 2: DensityMatrix ground, excited, and maximally mixed states and purity"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: DensityMatrix Von Neumann entropy
try {
    $pure = DensityMatrix::groundState();
    $mixed = DensityMatrix::maximumMixed();

    $sPure = $pure->vonNeumannEntropy();
    $sMixed = $mixed->vonNeumannEntropy();

    // Pure state entropy S = 0. Maximally mixed qubit S = ln(2) ~ 0.693147
    assertTest(
        abs($sPure) < 1e-10 &&
        abs($sMixed - log(2.0)) < 1e-5,
        "Test 3: Von Neumann entropy S(rho) = 0 for pure state and ln(2) for maximally mixed"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: DensityMatrix thermal equilibrium Gibbs state
try {
    $hbarOmega = 1.0e-21; // Joules
    $tempK = 300.0;       // Kelvin
    $thermal = DensityMatrix::thermalState($hbarOmega, $tempK);

    // rho11 / rho00 should equal exp(-hbar*omega / kT)
    $boltzmann = DensityMatrix::BOLTZMANN;
    $expectedRatio = exp(-$hbarOmega / ($boltzmann * $tempK));
    $actualRatio = $thermal->rho11() / $thermal->rho00();

    assertTest(
        abs($thermal->trace() - 1.0) < 1e-9 &&
        abs($actualRatio - $expectedRatio) < 1e-6 &&
        $thermal->rho00() > $thermal->rho11(),
        "Test 4: Thermal Gibbs state Boltzmann distribution rho11/rho00 = exp(-E/kT)"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: LindbladSolver unitary dynamics (pure rotation)
try {
    $omegaHz = 2.0 * M_PI * 1e9; // 1 GHz Larmor precession
    $solver = new LindbladSolver($omegaHz, [], 1e-11); // No dissipators

    // Start in superposition: rho00 = 0.5, rho11 = 0.5, rho01 = 0.5
    $initial = new DensityMatrix(0.5, 0.5, 0.5, 0.0);
    $evolved = $solver->evolve($initial, 50);

    // Unitary evolution preserves purity Tr(rho^2) = 1.0 and populations
    assertTest(
        abs($evolved->purity() - 1.0) < 1e-4 &&
        abs($evolved->rho00() - 0.5) < 1e-4 &&
        abs($evolved->rho11() - 0.5) < 1e-4 &&
        abs($evolved->trace() - 1.0) < 1e-6,
        "Test 5: LindbladSolver unitary evolution preserves trace, populations, and purity"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: LindbladSolver dissipative amplitude damping and relaxation
try {
    $omegaHz = 1e9;
    $gamma1 = 1e8; // Fast relaxation (T1 = 10 ns)
    $dissipators = [
        [DissipationChannel::AMPLITUDE_DAMPING, $gamma1],
    ];

    $solver = new LindbladSolver($omegaHz, $dissipators, 1e-10);
    $initialExcited = DensityMatrix::excitedState(); // rho11 = 1.0
    $final = $solver->evolve($initialExcited, 500); // 50 ns = 5*T1

    // Should decay towards ground state rho00 -> 1.0, rho11 -> 0.0
    assertTest(
        $final->rho00() > 0.95 &&
        $final->rho11() < 0.05 &&
        abs($final->trace() - 1.0) < 1e-6,
        "Test 6: LindbladSolver amplitude damping decays excited state toward ground state"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: QuantumHeatEngine Otto cycle efficiency and Carnot bound
try {
    $omegaC = 1.0e9;
    $omegaH = 2.0e9;
    $tempC = 100.0;
    $tempH = 300.0;

    $engine = new QuantumHeatEngine($omegaC, $omegaH, $tempC, $tempH);
    $res = $engine->runOttoCycle();

    $expectedOtto = 1.0 - ($omegaC / $omegaH); // 0.5
    $expectedCarnot = 1.0 - ($tempC / $tempH); // 0.6667

    assertTest(
        abs($res['otto_efficiency'] - $expectedOtto) < 1e-6 &&
        abs($res['carnot_efficiency'] - $expectedCarnot) < 1e-6 &&
        $res['otto_efficiency'] < $res['carnot_efficiency'] &&
        $res['work_output_joules'] > 0.0 &&
        $res['is_engine'] === true,
        "Test 7: QuantumHeatEngine Otto efficiency eta_Otto = 1 - wc/wh bounded by Carnot limit"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: QuantumHeatEngine refrigerator mode when frequency ratio is below temperature ratio
try {
    $omegaC = 1.0e9;
    $omegaH = 2.0e9; // omega_c / omega_h = 0.5
    $tempC = 250.0;
    $tempH = 300.0;  // T_c / T_h = 0.8333 => omega_c/omega_h < T_c/T_h operates as refrigerator!

    $refrigerator = new QuantumHeatEngine($omegaC, $omegaH, $tempC, $tempH);
    $res = $refrigerator->runOttoCycle();

    assertTest(
        $res['is_refrigerator'] === true &&
        $res['is_engine'] === false &&
        $res['work_output_joules'] < 0.0 &&
        $res['q_cold_joules'] > 0.0 &&
        $res['cop_refrigerator'] > 0.0,
        "Test 8: QuantumHeatEngine operates in refrigerator mode when wc/wh < Tc/Th"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: Landauer information erasure dissipation
try {
    $engine = new QuantumHeatEngine(1e9, 2e9, 100.0, 300.0);
    $landauer300 = $engine->landauerErasure(1.0, 300.0);

    // Q_min = k_B * T * ln(2)
    $expectedJoules = 1.380649e-23 * 300.0 * log(2.0);

    assertTest(
        abs($landauer300['landauer_limit_joules'] - $expectedJoules) < 1e-25 &&
        abs($landauer300['entropy_decrease_bits'] - 1.0) < 1e-9 &&
        $landauer300['landauer_limit_ev'] > 0.015 &&
        $landauer300['landauer_limit_ev'] < 0.020, // ~ 17.9 meV
        "Test 9: Landauer erasure minimum dissipation Q_min = kT ln(2) per bit"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: QuantumThermodynamicsManager facade and global helpers
try {
    $mgr = qtherm();
    $rhoFinal = qtherm_lindblad(1e9, [[DissipationChannel::DEPHASING, 1e7]], 1e-10, 20);
    $ottoRes = qtherm_engine(1e9, 2e9, 100.0, 300.0);
    $landauerRes = qtherm_landauer(2.0, 300.0);

    assertTest(
        $mgr instanceof QuantumThermodynamicsManager &&
        function_exists('qtherm') &&
        function_exists('qtherm_lindblad') &&
        function_exists('qtherm_engine') &&
        function_exists('qtherm_landauer') &&
        $rhoFinal instanceof DensityMatrix &&
        $ottoRes['otto_efficiency'] === 0.5 &&
        $landauerRes['bits_erased'] === 2.0,
        "Test 10: QuantumThermodynamicsManager facade and global helper functions"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

echo "=== Phase 119 Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
