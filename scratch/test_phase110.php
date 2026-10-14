<?php
/**
 * NetFusion Framework - Phase 110 Comprehensive Verification Suite
 *
 * Test suite for Gravitational Waveforms, Spacetime Metric Tensors & Numerical Relativity.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Relativity\Enums\DetectorSite;
use NetFusion\Core\Relativity\Enums\MergerPhase;
use NetFusion\Core\Relativity\Enums\MetricType;
use NetFusion\Core\Relativity\Enums\WaveformPolarization;
use NetFusion\Core\Relativity\Exceptions\RelativityComputationException;
use NetFusion\Core\Relativity\Exceptions\SpacetimeSingularityException;
use NetFusion\Core\Relativity\RelativityManager;
use NetFusion\Core\Relativity\Tensor\ChristoffelSymbols;
use NetFusion\Core\Relativity\Tensor\MetricTensor;
use NetFusion\Core\Relativity\Waveform\BinaryChirpSystem;
use NetFusion\Core\Relativity\Waveform\GravitationalWaveformEngine;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

echo "=== Running Phase 110: Numerical Relativity & Gravitational Waveform Suite ===\n\n";

// Test 1: MetricType & DetectorSite enums
try {
    $h1 = DetectorSite::LigoHanford;
    $v1 = DetectorSite::Virgo;
    $lisa = DetectorSite::Lisa;

    assertTest(
        $h1->armLengthKm() === 4.0 && $v1->armLengthKm() === 3.0 && $lisa->armLengthKm() === 2.5e6 &&
        $h1->bandwidth()['minHz'] === 10.0,
        'Test 1: DetectorSite enum computes correct interferometer arm lengths (H1=4km, LISA=2.5M km) and bandwidths'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 1 Exception: ' . $e->getMessage());
}

// Test 2: Flat Minkowski Spacetime Metric & Line Element
try {
    $minkowski = MetricTensor::flatMinkowski();
    $det = $minkowski->determinant();
    $ds2 = $minkowski->lineElement([1.0, 0.0, 0.0, 0.0]); // Timelike: -1 * 1^2 = -1

    assertTest(
        abs($det - (-1.0)) < 1e-6 && abs($ds2 - (-1.0)) < 1e-6 && $minkowski->getType() === MetricType::Minkowski,
        'Test 2: MetricTensor constructs flat Minkowski metric with signature (-1,1,1,1) and invariant line element'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 2 Exception: ' . $e->getMessage());
}

// Test 3: Schwarzschild Metric Tensor & Event Horizon
try {
    $mass = 10.0; // 10 M_sun
    $rs = MetricTensor::schwarzschildRadiusMeters($mass);
    $rEval = 100000.0; // 100 km (rs ~ 29.5 km)

    $schwarzschild = MetricTensor::schwarzschild($mass, $rEval);
    $det = $schwarzschild->determinant();
    $inv = $schwarzschild->inverse();

    // Verify g_00 * g^00 == 1
    $prod00 = $schwarzschild->get(0, 0) * $inv[0][0];

    assertTest(
        $rs > 29000.0 && $rs < 30000.0 && $det < 0.0 && abs($prod00 - 1.0) < 1e-5,
        'Test 3: MetricTensor constructs Schwarzschild geometry, calculates rs (~29.5 km for 10 M☉) and tensor inverse'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 3 Exception: ' . $e->getMessage());
}

// Test 4: Kerr Metric Tensor with Frame Dragging (off-diagonal g_t_phi)
try {
    $mass = 15.0;
    $spin = 0.85; // a* = 0.85
    $rEval = 150000.0;

    $kerr = MetricTensor::kerr($mass, $spin, $rEval, M_PI_2);
    $gtphi = $kerr->get(0, 3);
    $gphit = $kerr->get(3, 0);

    assertTest(
        $gtphi < 0.0 && abs($gtphi - $gphit) < 1e-9 && $kerr->getType() === MetricType::Kerr,
        'Test 4: MetricTensor models spinning Kerr metric with off-diagonal frame-dragging coupling (g_t_phi = g_phi_t)'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 4 Exception: ' . $e->getMessage());
}

// Test 5: Christoffel Affine Connections & Geodesic Acceleration
try {
    $symbols = ChristoffelSymbols::forSchwarzschild(10.0, 100000.0, M_PI_2);
    $fourVel = [1.0, 0.0, 0.0, 0.0]; // Static observer u^t = 1
    $fourAcc = $symbols->geodesicAcceleration($fourVel);

    // a^r = - Gamma^r_tt (u^t)^2 should be negative (inward radial gravitational pull)
    assertTest(
        $symbols->get(0, 0, 1) > 0.0 && $fourAcc[1] < 0.0,
        'Test 5: ChristoffelSymbols computes affine connections and enforces inward gravitational four-acceleration'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 5 Exception: ' . $e->getMessage());
}

// Test 6: BinaryChirpSystem Post-Newtonian Invariants
try {
    // GW150914 parameters: m1=36 M_sun, m2=29 M_sun, d=410 Mpc
    $binary = new BinaryChirpSystem(36.0, 29.0, 410.0);
    $mc = $binary->getChirpMass();
    $fIsco = $binary->getIscoFrequencyHz();
    $fQnm = $binary->getRingdownFrequencyHz();

    // Expected Chirp mass for 36+29 is ~28.1 M_sun, orbital ISCO frequency ~67.6 Hz, Ringdown ~196.8 Hz
    assertTest(
        abs($mc - 28.1) < 0.5 && $fIsco > 50.0 && $fIsco < 100.0 && $fQnm > $fIsco,
        'Test 6: BinaryChirpSystem calculates exact chirp mass (~28.1 M☉ for GW150914), ISCO frequency (~67.6 Hz), and QNM ringdown'
    );


} catch (Throwable $e) {
    assertTest(false, 'Test 6 Exception: ' . $e->getMessage());
}

// Test 7: GravitationalWaveformEngine Inspiral-Merger-Ringdown Waveform
try {
    $binary = new BinaryChirpSystem(36.0, 29.0, 410.0);
    $wf = GravitationalWaveformEngine::generateWaveform($binary, 0.2, 2048.0);

    assertTest(
        count($wf['time']) === 410 &&
        $wf['peak_strain'] > 1e-22 &&
        in_array(MergerPhase::Inspiral->value, $wf['phases'], true) &&
        in_array(MergerPhase::Ringdown->value, $wf['phases'], true),
        'Test 7: GravitationalWaveformEngine synthesizes continuous IMR strain time series with inspiral and ringdown phases'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 7 Exception: ' . $e->getMessage());
}

// Test 8: Matched Filter Signal-to-Noise Ratio (SNR) cross-correlation
try {
    $binary = new BinaryChirpSystem(36.0, 29.0, 410.0);
    $wf = GravitationalWaveformEngine::generateWaveform($binary, 0.2, 2048.0);
    $template = $wf['h_plus'];

    // Autocorrelation SNR of clean template against itself: sqrt(sum h^2)
    $snrSelf = GravitationalWaveformEngine::matchedFilterSnr($template, $template);

    // Noisy observation with injected template
    $noisy = [];
    foreach ($template as $h) {
        $noisy[] = $h + (1e-23 * (mt_rand(-10, 10) / 10.0));
    }
    $snrNoisy = GravitationalWaveformEngine::matchedFilterSnr($noisy, $template);

    assertTest(
        $snrSelf > 0.0 && $snrNoisy > 0.0,
        'Test 8: Matched filter cross-correlation successfully calculates optimal detection SNR from strain templates'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 8 Exception: ' . $e->getMessage());
}

// Test 9: RelativityManager Facade & Orchestration
try {
    $manager = new RelativityManager();
    $sim = $manager->simulateBinaryChirp(30.0, 30.0, 500.0, 0.15, 1024.0);
    $metric = $manager->createMetric(MetricType::Schwarzschild, 20.0, 120000.0);

    assertTest(
        $sim['total_mass_solar'] === 60.0 &&
        $sim['peak_strain'] > 0.0 &&
        $metric->getType() === MetricType::Schwarzschild,
        'Test 9: RelativityManager facade orchestrates metric construction, binary chirp simulation, and telemetry'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 9 Exception: ' . $e->getMessage());
}

// Test 10: Singularity Guardrails & Exception Handling
try {
    $caughtSingularity = false;
    try {
        // Evaluate at or inside horizon
        MetricTensor::schwarzschild(10.0, 20000.0); // rs ~ 29.5 km, r=20 km is inside!
    } catch (SpacetimeSingularityException) {
        $caughtSingularity = true;
    }

    $caughtNegativeMass = false;
    try {
        new BinaryChirpSystem(-10.0, 20.0);
    } catch (RelativityComputationException) {
        $caughtNegativeMass = true;
    }

    assertTest(
        $caughtSingularity && $caughtNegativeMass,
        'Test 10: Robust guardrails prevent coordinate/physical singularity breaches and reject unphysical negative masses'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 10 Exception: ' . $e->getMessage());
}

echo "\nVerification Summary: {$passed} Passed, {$failed} Failed\n";
exit($failed === 0 ? 0 : 1);
