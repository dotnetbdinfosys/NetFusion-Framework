<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Holographic MIMO beamformer optimizing end-to-end cascaded channels and SNR.
 *
 * @package    NetFusion\Wireless\Ris\Beamforming
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Beamforming;

use NetFusion\Core\Photonic\Physics\ComplexNumber;
use NetFusion\Core\Wireless\Ris\Enums\BeamformingAlgorithm;
use NetFusion\Core\Wireless\Ris\Enums\ChannelState;
use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Exceptions\TerahertzChannelException;
use NetFusion\Core\Wireless\Ris\Physics\ChannelMatrix;
use NetFusion\Core\Wireless\Ris\Physics\WaveVector;
use NetFusion\Core\Wireless\Ris\Surface\RisArray;

class HolographicBeamformer
{
    /**
     * Compute end-to-end effective channel:
     * H_eff = H_direct + G * Phi * F
     * where:
     *   H_direct: Tx -> Rx direct link matrix (Rx x Tx)
     *   F: Tx -> RIS link matrix (RIS_elements x Tx)
     *   Phi: RIS diagonal reflection matrix (RIS_elements x RIS_elements)
     *   G: RIS -> Rx link matrix (Rx x RIS_elements)
     */
    public static function computeEffectiveChannel(
        ChannelMatrix $directChannel,
        ChannelMatrix $txToRisChannel,
        RisArray $risArray,
        ChannelMatrix $risToRxChannel
    ): ChannelMatrix {
        $phi = $risArray->getDiagonalReflectionMatrix();

        // G * Phi
        $gPhi = $risToRxChannel->multiply($phi);

        // G * Phi * F
        $cascaded = $gPhi->multiply($txToRisChannel);

        // H_eff = H_direct + Cascaded
        return $directChannel->add($cascaded);
    }

    /**
     * Synthesize channel matrix between a transmitter/receiver and the RIS array
     * based on spatial wavevectors, distances, and path loss.
     *
     * @param int $antennas Number of antennas on endpoint (Tx or Rx)
     * @param bool $isEndpointToRis True if Tx -> RIS, False if RIS -> Rx
     */
    public static function synthesizeLoSChannel(
        RisArray $risArray,
        WaveVector $waveVector,
        float $distanceMeters,
        int $antennas = 1,
        ChannelState $state = ChannelState::LineOfSight,
        bool $isEndpointToRis = true
    ): ChannelMatrix {
        if ($distanceMeters <= 0.0) {
            throw new TerahertzChannelException('Link distance must be strictly positive.');
        }

        $fspl = $waveVector->calculateFreeSpaceLoss($distanceMeters);
        $molecularLoss = $waveVector->calculateMolecularAbsorptionLoss($distanceMeters);
        $penaltyDb = $state->attenuationPenaltyDb();
        $stateFactor = pow(10.0, -$penaltyDb / 10.0);

        // Amplitude attenuation = sqrt(molecularLoss * stateFactor / fspl)
        $amplitude = sqrt(($molecularLoss * $stateFactor) / $fspl);

        $steering = SteeringVectorEngine::computeSteeringVector($risArray, $waveVector);
        $risSize = $risArray->getTotalElements();

        if ($isEndpointToRis) {
            // Channel matrix F has size: [risSize, antennas]
            $matrix = ChannelMatrix::zeros($risSize, $antennas);
            for ($r = 0; $r < $risSize; $r++) {
                for ($a = 0; $a < $antennas; $a++) {
                    // Endpoint antenna phase shift (simplified uniform linear array)
                    $antennaPhase = $a * (M_PI / 2.0);
                    $coeff = $steering[$r]->scale($amplitude);
                    $coeff = $coeff->multiply(ComplexNumber::fromPolar(1.0, $antennaPhase));
                    $matrix->set($r, $a, $coeff);
                }
            }
            return $matrix;
        } else {
            // Channel matrix G has size: [antennas, risSize]
            $matrix = ChannelMatrix::zeros($antennas, $risSize);
            for ($a = 0; $a < $antennas; $a++) {
                $antennaPhase = $a * (M_PI / 2.0);
                for ($c = 0; $c < $risSize; $c++) {
                    $coeff = $steering[$c]->conjugate()->scale($amplitude);
                    $coeff = $coeff->multiply(ComplexNumber::fromPolar(1.0, -$antennaPhase));
                    $matrix->set($a, $c, $coeff);
                }
            }
            return $matrix;
        }
    }

    /**
     * Synthesize direct Tx -> Rx channel.
     */
    public static function synthesizeDirectChannel(
        FrequencyBand $band,
        float $distanceMeters,
        int $rxAntennas = 1,
        int $txAntennas = 1,
        ChannelState $state = ChannelState::Obstructed
    ): ChannelMatrix {
        $dummyWave = new WaveVector($band, 0.0, 0.0);
        $fspl = $dummyWave->calculateFreeSpaceLoss($distanceMeters);
        $molecularLoss = $dummyWave->calculateMolecularAbsorptionLoss($distanceMeters);
        $penaltyDb = $state->attenuationPenaltyDb();
        $stateFactor = pow(10.0, -$penaltyDb / 10.0);

        $amplitude = sqrt(($molecularLoss * $stateFactor) / $fspl);

        $matrix = ChannelMatrix::zeros($rxAntennas, $txAntennas);
        for ($i = 0; $i < $rxAntennas; $i++) {
            for ($j = 0; $j < $txAntennas; $j++) {
                $phase = ($i * 0.3 + $j * 0.5);
                $matrix->set($i, $j, ComplexNumber::fromPolar($amplitude, $phase));
            }
        }
        return $matrix;
    }

    /**
     * Optimize RIS phases to maximize received SNR using Maximum Ratio Transmission (MRT).
     * Optimal phase on element n: phi_n = arg(h_direct) - arg(g_n) - arg(f_n)
     *
     * @param ChannelMatrix $txToRisChannel F of size [N, 1]
     * @param ChannelMatrix $risToRxChannel G of size [1, N]
     * @param ChannelMatrix $directChannel H_d of size [1, 1]
     */
    public static function optimizeMrtPhases(
        RisArray $risArray,
        ChannelMatrix $txToRisChannel,
        ChannelMatrix $risToRxChannel,
        ChannelMatrix $directChannel
    ): void {
        $n = $risArray->getTotalElements();
        $rows = $risArray->getRows();
        $cols = $risArray->getCols();

        $directPhase = $directChannel->get(0, 0)->argument();

        $phaseMatrix = [];
        $idx = 0;
        for ($r = 0; $r < $rows; $r++) {
            $rowPhases = [];
            for ($c = 0; $c < $cols; $c++) {
                $fe = $txToRisChannel->get($idx, 0);
                $ge = $risToRxChannel->get(0, $idx);

                $fp = $fe->argument();
                $gp = $ge->argument();

                // To coherently align with direct channel:
                // arg(g_n * e^(j*phi_n) * f_n) == directPhase
                // phi_n = directPhase - (gPhase + fPhase)
                $optimalPhase = $directPhase - ($gp + $fp);
                $rowPhases[] = $optimalPhase;
                $idx++;
            }
            $phaseMatrix[] = $rowPhases;
        }


        $risArray->configurePhaseMatrix($phaseMatrix);
    }

    /**
     * Perform full beamforming evaluation and return metrics.
     *
     * @return array{
     *     direct_capacity_bps_hz: float,
     *     effective_capacity_bps_hz: float,
     *     snr_gain_db: float,
     *     quantization_error_rad: float,
     *     total_elements: int,
     *     algorithm: string
     * }
     */
    public static function evaluatePerformance(
        RisArray $risArray,
        ChannelMatrix $txToRisChannel,
        ChannelMatrix $risToRxChannel,
        ChannelMatrix $directChannel,
        float $txPowerWatts = 1.0,
        float $noisePowerWatts = 1e-12,
        BeamformingAlgorithm $algorithm = BeamformingAlgorithm::MaximumRatio
    ): array {
        $snrLinear = $txPowerWatts / max(1e-15, $noisePowerWatts);

        // 1. Direct channel capacity without RIS
        $directCapacity = $directChannel->estimateCapacity($snrLinear);
        $directFrobeniusSq = $directChannel->frobeniusNormSquared();

        // 2. Optimize RIS phase
        if ($algorithm === BeamformingAlgorithm::MaximumRatio) {
            self::optimizeMrtPhases($risArray, $txToRisChannel, $risToRxChannel, $directChannel);
        }

        // 3. Effective channel capacity with RIS
        $effectiveChannel = self::computeEffectiveChannel(
            $directChannel,
            $txToRisChannel,
            $risArray,
            $risToRxChannel
        );
        $effectiveCapacity = $effectiveChannel->estimateCapacity($snrLinear);
        $effectiveFrobeniusSq = $effectiveChannel->frobeniusNormSquared();

        // 4. SNR Gain in dB: 10 * log10(||H_eff||_F^2 / ||H_dir||_F^2)
        $ratio = ($directFrobeniusSq > 0.0)
            ? ($effectiveFrobeniusSq / $directFrobeniusSq)
            : ($effectiveFrobeniusSq * 1e10);
        $gainDb = 10.0 * log10(max(1.0, $ratio));

        return [
            'direct_capacity_bps_hz'    => round($directCapacity, 4),
            'effective_capacity_bps_hz' => round($effectiveCapacity, 4),
            'snr_gain_db'               => round($gainDb, 2),
            'quantization_error_rad'    => round($risArray->getAverageQuantizationError(), 4),
            'total_elements'            => $risArray->getTotalElements(),
            'algorithm'                 => $algorithm->value,
        ];
    }
}

