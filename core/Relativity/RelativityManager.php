<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Core manager facade for Numerical Relativity and Gravitational Waveforms.
 *
 * @package    NetFusion\Core\Relativity
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity;

use NetFusion\Core\Relativity\Enums\DetectorSite;
use NetFusion\Core\Relativity\Enums\MetricType;
use NetFusion\Core\Relativity\Exceptions\RelativityComputationException;
use NetFusion\Core\Relativity\Tensor\ChristoffelSymbols;
use NetFusion\Core\Relativity\Tensor\MetricTensor;
use NetFusion\Core\Relativity\Waveform\BinaryChirpSystem;
use NetFusion\Core\Relativity\Waveform\GravitationalWaveformEngine;

class RelativityManager
{
    /**
     * Create a 4D spacetime metric tensor.
     */
    public function createMetric(
        MetricType $type,
        float $massSolar = 10.0,
        float $radiusMeters = 100000.0,
        float $spinParameter = 0.7,
        float $thetaRad = M_PI_2
    ): MetricTensor {
        return match ($type) {
            MetricType::Minkowski     => MetricTensor::flatMinkowski(),
            MetricType::Schwarzschild => MetricTensor::schwarzschild($massSolar, $radiusMeters, $thetaRad),
            MetricType::Kerr          => MetricTensor::kerr($massSolar, $spinParameter, $radiusMeters, $thetaRad),
            default                   => MetricTensor::flatMinkowski(),
        };
    }

    /**
     * Compute Christoffel affine connections for Schwarzschild spacetime.
     */
    public function getChristoffelSymbols(float $massSolar, float $radiusMeters, float $thetaRad = M_PI_2): ChristoffelSymbols
    {
        return ChristoffelSymbols::forSchwarzschild($massSolar, $radiusMeters, $thetaRad);
    }

    /**
     * Simulate a binary coalescence waveform.
     *
     * @return array{
     *     chirp_mass_solar: float,
     *     total_mass_solar: float,
     *     remnant_mass_solar: float,
     *     isco_frequency_hz: float,
     *     ringdown_frequency_hz: float,
     *     peak_strain: float,
     *     num_samples: int,
     *     waveform: array{time: float[], h_plus: float[], h_cross: float[], frequency: float[], phases: string[], peak_strain: float}
     * }
     */
    public function simulateBinaryChirp(
        float $m1Solar = 36.0,
        float $m2Solar = 29.0,
        float $distanceMpc = 410.0,
        float $durationSec = 0.2,
        float $sampleRateHz = 2048.0
    ): array {
        $system = new BinaryChirpSystem($m1Solar, $m2Solar, $distanceMpc);
        $wf = GravitationalWaveformEngine::generateWaveform($system, $durationSec, $sampleRateHz);

        return [
            'chirp_mass_solar'      => round($system->getChirpMass(), 2),
            'total_mass_solar'      => round($system->getTotalMass(), 2),
            'remnant_mass_solar'    => round($system->getRemnantMassSolar(), 2),
            'isco_frequency_hz'     => round($system->getIscoFrequencyHz(), 2),
            'ringdown_frequency_hz' => round($system->getRingdownFrequencyHz(), 2),
            'peak_strain'           => $wf['peak_strain'],
            'num_samples'           => count($wf['time']),
            'waveform'              => $wf,
        ];
    }

    /**
     * Evaluate matched filtering detection of a template in detector strain.
     */
    public function detectSignal(array $strainData, array $templateWaveform): float
    {
        return GravitationalWaveformEngine::matchedFilterSnr($strainData, $templateWaveform);
    }
}
