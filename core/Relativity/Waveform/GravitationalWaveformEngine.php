<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Engine
 *
 * Gravitational waveform generator (Inspiral-Merger-Ringdown) and matched filter detector.
 *
 * @package    NetFusion\Core\Relativity\Waveform
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Relativity\Waveform;

use NetFusion\Core\Relativity\Enums\MergerPhase;
use NetFusion\Core\Relativity\Enums\WaveformPolarization;
use NetFusion\Core\Relativity\Exceptions\RelativityComputationException;

class GravitationalWaveformEngine
{
    /**
     * Generate discrete time series of gravitational strain h_+(t) and h_x(t).
     *
     * @param float $durationSec Total waveform duration before merger (e.g. 0.2s for GW150914)
     * @param float $sampleRateHz Sampling frequency (e.g. 4096 Hz)
     * @return array{
     *     time: array<int, float>,
     *     h_plus: array<int, float>,
     *     h_cross: array<int, float>,
     *     frequency: array<int, float>,
     *     phases: array<int, string>,
     *     peak_strain: float
     * }
     */
    public static function generateWaveform(
        BinaryChirpSystem $system,
        float $durationSec = 0.2,
        float $sampleRateHz = 2048.0
    ): array {
        if ($durationSec <= 0.0 || $sampleRateHz <= 0.0) {
            throw new RelativityComputationException('Duration and sample rate must be positive.');
        }

        $numSamples = (int) round($durationSec * $sampleRateHz);
        $dt = 1.0 / $sampleRateHz;

        $tMerger = $durationSec * 0.85; // Merger occurs at 85% of time window
        $fIsco = $system->getIscoFrequencyHz();
        $fQnm = $system->getRingdownFrequencyHz();
        $tauQnm = $system->getRingdownDampingTimeSec();

        $chirpMassKg = $system->getChirpMass() * BinaryChirpSystem::SOLAR_MASS_KG;
        $distMeters = $system->getDistanceMeters();
        $iota = $system->getInclination();

        $times = [];
        $hPlus = [];
        $hCross = [];
        $freqs = [];
        $phases = [];

        $peakStrain = 0.0;
        $phaseAccum = 0.0;

        for ($i = 0; $i < $numSamples; $i++) {
            $t = $i * $dt;
            $times[] = round($t, 6);

            if ($t < $tMerger) {
                // 1. Inspiral phase: Leading-order Post-Newtonian (0PN) chirp
                // tau_to_merger = t_merger - t
                $tau = max(1e-4, $tMerger - $t);

                // f_gw(tau) = (1 / pi) * (5 / 256 / tau)^(3/8) * (G * M_c / c^3)^(-5/8)
                $gMcOverC3 = (BinaryChirpSystem::GRAVITATIONAL_CONSTANT * $chirpMassKg) / pow(BinaryChirpSystem::SPEED_OF_LIGHT, 3);
                $fGw = (1.0 / M_PI) * pow(5.0 / (256.0 * $tau), 3.0 / 8.0) * pow($gMcOverC3, -5.0 / 8.0);
                $fGw = min($fGw, $fIsco);

                // Strain amplitude A(t) = 4 * (G * M_c / c^2) * (pi * G * M_c * f / c^3)^(2/3) / d
                $gMcOverC2 = (BinaryChirpSystem::GRAVITATIONAL_CONSTANT * $chirpMassKg) / pow(BinaryChirpSystem::SPEED_OF_LIGHT, 2);
                $amp = (4.0 * $gMcOverC2 / $distMeters) * pow(M_PI * $gMcOverC3 * $fGw, 2.0 / 3.0);

                $phaseAccum += 2.0 * M_PI * $fGw * $dt;

                $plusFactor = -(1.0 + pow(cos($iota), 2)) / 2.0;
                $crossFactor = -cos($iota);

                $hp = $amp * $plusFactor * cos($phaseAccum);
                $hx = $amp * $crossFactor * sin($phaseAccum);

                $phaseType = ($fGw >= 0.85 * $fIsco) ? MergerPhase::Plunge->value : MergerPhase::Inspiral->value;
            } elseif ($t >= $tMerger && $t < ($tMerger + 5.0 * $tauQnm)) {
                // 2. Ringdown phase: Damped sinusoid
                $dtRing = $t - $tMerger;
                $decay = exp(-$dtRing / $tauQnm);

                // Amplitude matched at merger
                $ampMerger = (4.0 * ((BinaryChirpSystem::GRAVITATIONAL_CONSTANT * $chirpMassKg) / pow(BinaryChirpSystem::SPEED_OF_LIGHT, 2)) / $distMeters)
                    * pow(M_PI * ((BinaryChirpSystem::GRAVITATIONAL_CONSTANT * $chirpMassKg) / pow(BinaryChirpSystem::SPEED_OF_LIGHT, 3)) * $fIsco, 2.0 / 3.0) * 1.5;

                $phaseAccum += 2.0 * M_PI * $fQnm * $dt;
                $fGw = $fQnm;

                $hp = $ampMerger * $decay * cos($phaseAccum);
                $hx = $ampMerger * $decay * sin($phaseAccum);

                $phaseType = ($dtRing < $tauQnm) ? MergerPhase::Merger->value : MergerPhase::Ringdown->value;
            } else {
                // Quiescent remnant
                $hp = 0.0;
                $hx = 0.0;
                $fGw = 0.0;
                $phaseType = MergerPhase::Ringdown->value;
            }

            $hPlus[] = $hp;
            $hCross[] = $hx;
            $freqs[] = round($fGw, 2);
            $phases[] = $phaseType;

            $mag = hypot($hp, $hx);
            if ($mag > $peakStrain) {
                $peakStrain = $mag;
            }
        }

        return [
            'time'        => $times,
            'h_plus'      => $hPlus,
            'h_cross'     => $hCross,
            'frequency'   => $freqs,
            'phases'      => $phases,
            'peak_strain' => $peakStrain,
        ];
    }

    /**
     * Compute matched filter cross-correlation and optimal Signal-to-Noise Ratio (SNR).
     * SNR = (d | h) / sqrt(h | h)
     *
     * @param array<int, float> $strainData Observed detector strain time series s(t)
     * @param array<int, float> $templateWaveform Template strain time series h(t)
     */
    public static function matchedFilterSnr(array $strainData, array $templateWaveform): float
    {
        $n = min(count($strainData), count($templateWaveform));
        if ($n === 0) {
            return 0.0;
        }

        $crossDot = 0.0;
        $templateNormSq = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $crossDot += $strainData[$i] * $templateWaveform[$i];
            $templateNormSq += $templateWaveform[$i] * $templateWaveform[$i];
        }

        if ($templateNormSq <= 0.0) {
            return 0.0;
        }

        return abs($crossDot) / sqrt($templateNormSq);
    }
}
