<?php
/**
 * NetFusion Framework - Phase 109 Comprehensive Verification Suite
 *
 * Test suite for 6G Wireless RIS, THz beamforming, and metamaterials.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Photonic\Physics\ComplexNumber;
use NetFusion\Core\Wireless\Ris\Beamforming\HolographicBeamformer;
use NetFusion\Core\Wireless\Ris\Beamforming\SteeringVectorEngine;
use NetFusion\Core\Wireless\Ris\Enums\BeamformingAlgorithm;
use NetFusion\Core\Wireless\Ris\Enums\ChannelState;
use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Enums\PhaseResolution;
use NetFusion\Core\Wireless\Ris\Exceptions\RisConfigurationException;
use NetFusion\Core\Wireless\Ris\Exceptions\TerahertzChannelException;
use NetFusion\Core\Wireless\Ris\Physics\ChannelMatrix;
use NetFusion\Core\Wireless\Ris\Physics\WaveVector;
use NetFusion\Core\Wireless\Ris\RisManager;
use NetFusion\Core\Wireless\Ris\Surface\MetamaterialElement;
use NetFusion\Core\Wireless\Ris\Surface\RisArray;


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

echo "=== Running Phase 109: 6G Wireless RIS Verification Suite ===\n\n";

// Test 1: FrequencyBand properties & molecular absorption
try {
    $thz = FrequencyBand::Terahertz;
    $wl = $thz->wavelengthMeters();
    $absorption = $thz->molecularAbsorptionDbPerKm();
    assertTest(
        abs($thz->frequencyHz() - 300.0e9) < 1.0 && $wl > 0 && $absorption === 10.5,
        'Test 1: FrequencyBand calculates correct THz frequency (300 GHz), wavelength, and molecular absorption (10.5 dB/km)'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 1 Exception: ' . $e->getMessage());
}

// Test 2: PhaseResolution discrete quantization
try {
    $resOneBit = PhaseResolution::OneBit;
    $resTwoBit = PhaseResolution::TwoBit;

    // Angle 0.1 rad -> should quantize to 0.0 in 1-bit
    $q1 = $resOneBit->quantize(0.1);
    // Angle 3.0 rad (near pi = 3.14159) -> should quantize to pi in 1-bit
    $q2 = $resOneBit->quantize(3.0);
    // Angle 1.5 rad (near pi/2 = 1.5708) -> should quantize to pi/2 in 2-bit
    $q3 = $resTwoBit->quantize(1.5);

    assertTest(
        abs($q1 - 0.0) < 1e-4 && abs($q2 - M_PI) < 1e-4 && abs($q3 - (M_PI / 2.0)) < 1e-4,
        'Test 2: PhaseResolution accurately quantizes continuous phases to 1-bit and 2-bit discrete states'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 2 Exception: ' . $e->getMessage());
}

// Test 3: WaveVector 3D electromagnetic spatial vector
try {
    $wave = new WaveVector(FrequencyBand::Terahertz, deg2rad(45), deg2rad(30));
    $fspl = $wave->calculateFreeSpaceLoss(10.0);
    $molLoss = $wave->calculateMolecularAbsorptionLoss(100.0);

    assertTest(
        $wave->getWavenumber() > 0 && $fspl > 1.0 && ($molLoss > 0.0 && $molLoss < 1.0),
        'Test 3: WaveVector correctly calculates wavenumber, Free Space Path Loss (FSPL), and molecular attenuation factor'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 3 Exception: ' . $e->getMessage());
}

// Test 4: ChannelMatrix arithmetic and Frobenius norm
try {
    $m1 = ChannelMatrix::zeros(2, 2);
    $m1->set(0, 0, new ComplexNumber(1.0, 2.0));
    $m1->set(1, 1, new ComplexNumber(3.0, 4.0));

    $m2 = ChannelMatrix::identity(2);
    $prod = $m1->multiply($m2);
    $norm = $prod->frobeniusNorm();

    // Elements: (1+2j) and (3+4j), normSq = (1+4) + (9+16) = 5 + 25 = 30, norm = sqrt(30) ~ 5.477
    assertTest(
        abs($norm - sqrt(30.0)) < 1e-4 && abs($prod->get(0, 0)->getReal() - 1.0) < 1e-5,
        'Test 4: ChannelMatrix correctly implements complex multiplication, identity preservation, and Frobenius norm'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 4 Exception: ' . $e->getMessage());
}

// Test 5: MetamaterialElement reflection coefficient and quantization error
try {
    $elem = new MetamaterialElement(0, 0, deg2rad(45), 0.9, PhaseResolution::OneBit);
    $coeff = $elem->getReflectionCoefficient();
    $error = $elem->getQuantizationPhaseError();

    // 45 deg = pi/4 ~ 0.785 rad. In 1-bit {0, pi}, round(0.785 / 3.14159) = 0.
    // Quantized = 0, ideal = 0.785, error = 0.785 rad.
    assertTest(
        abs($elem->getAmplitude() - 0.9) < 1e-5 && abs($coeff->magnitude() - 0.9) < 1e-5 && abs($error - deg2rad(45)) < 1e-4,
        'Test 5: MetamaterialElement accurately enforces reflection coefficient and tracks phase quantization error'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 5 Exception: ' . $e->getMessage());
}

// Test 6: RisArray planar grid initialization and reflection matrix
try {
    $array = new RisArray('test_grid', 4, 4, FrequencyBand::Terahertz, PhaseResolution::Continuous);
    $phi = $array->getDiagonalReflectionMatrix();
    $coords = $array->getPhysicalCoordinates(0, 0);

    assertTest(
        $array->getTotalElements() === 16 && $phi->getRows() === 16 && $phi->getCols() === 16 && isset($coords['x'], $coords['y']),
        'Test 6: RisArray planar grid properly constructs 4x4 array and 16x16 diagonal reflection matrix Phi'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 6 Exception: ' . $e->getMessage());
}

// Test 7: SteeringVectorEngine 3D beam steering & phase compensation
try {
    $array = new RisArray('steer_test', 4, 4, FrequencyBand::Terahertz);
    $inWave = new WaveVector(FrequencyBand::Terahertz, 0.0, deg2rad(20));
    $outWave = new WaveVector(FrequencyBand::Terahertz, deg2rad(60), deg2rad(15));

    $phaseMatrix = SteeringVectorEngine::computeAnomalousReflectionPhase($array, $inWave, $outWave);
    $array->configurePhaseMatrix($phaseMatrix);

    $steering = SteeringVectorEngine::computeSteeringVector($array, $outWave);
    $gain = SteeringVectorEngine::calculateArrayGain($array, $outWave, $steering);

    assertTest(
        count($phaseMatrix) === 4 && count($phaseMatrix[0]) === 4 && $gain > 0.0,
        'Test 7: SteeringVectorEngine computes anomalous reflection phase profile and positive array directivity gain'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 7 Exception: ' . $e->getMessage());
}

// Test 8: HolographicBeamformer effective cascaded channel & SNR enhancement
try {
    $array = new RisArray('beam_test', 4, 4, FrequencyBand::Terahertz);
    $dummyWave = new WaveVector(FrequencyBand::Terahertz, 0.0, 0.0);

    $txToRis = HolographicBeamformer::synthesizeLoSChannel($array, $dummyWave, 10.0, 1, ChannelState::LineOfSight, true);
    $risToRx = HolographicBeamformer::synthesizeLoSChannel($array, $dummyWave, 10.0, 1, ChannelState::LineOfSight, false);
    $direct = HolographicBeamformer::synthesizeDirectChannel(FrequencyBand::Terahertz, 20.0, 1, 1, ChannelState::Obstructed);

    $perf = HolographicBeamformer::evaluatePerformance(
        $array,
        $txToRis,
        $risToRx,
        $direct,
        1.0,
        1e-12,
        BeamformingAlgorithm::MaximumRatio
    );

    assertTest(
        $perf['effective_capacity_bps_hz'] >= $perf['direct_capacity_bps_hz'] && $perf['snr_gain_db'] >= 0.0,
        'Test 8: HolographicBeamformer MRT phase alignment delivers strictly non-negative SNR gain over obstructed channel'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 8 Exception: ' . $e->getMessage());
}

// Test 9: RisManager lifecycle, steering and channel simulation facade
try {
    $manager = new RisManager();
    $array = $manager->createArray('facade_ris', 6, 6, FrequencyBand::Terahertz, PhaseResolution::TwoBit);
    
    $steerRes = $manager->steerBeam('facade_ris', 10.0, 30.0, 45.0, 20.0);
    $channelSim = $manager->simulateChannelLink('facade_ris', 12.0, 8.0, 18.0);

    assertTest(
        $steerRes['elements'] === 36 && $channelSim['carrier_freq_ghz'] === 300.0 && $channelSim['snr_gain_db'] >= 0.0,
        'Test 9: RisManager facade orchestrates surface creation, steering, and full link simulation telemetry'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 9 Exception: ' . $e->getMessage());
}

// Test 10: Exception handling & invalid dimensions guard
try {
    $caughtDim = false;
    try {
        new RisArray('invalid', -4, 4);
    } catch (RisConfigurationException) {
        $caughtDim = true;
    }

    $caughtDist = false;
    try {
        $wave = new WaveVector(FrequencyBand::Terahertz, 0.0, 0.0);
        $wave->calculateFreeSpaceLoss(-5.0);
    } catch (TerahertzChannelException) {
        $caughtDist = true;
    }

    assertTest(
        $caughtDim && $caughtDist,
        'Test 10: Robust exception handling prevents invalid array dimensions and negative channel distances'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 10 Exception: ' . $e->getMessage());
}

echo "\nVerification Summary: {$passed} Passed, {$failed} Failed\n";
exit($failed === 0 ? 0 : 1);
