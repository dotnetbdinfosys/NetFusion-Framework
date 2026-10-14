<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * High-level manager and facade for Reconfigurable Intelligent Surface (RIS) operations.
 *
 * @package    NetFusion\Wireless\Ris
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris;

use NetFusion\Core\Wireless\Ris\Beamforming\HolographicBeamformer;
use NetFusion\Core\Wireless\Ris\Beamforming\SteeringVectorEngine;
use NetFusion\Core\Wireless\Ris\Enums\BeamformingAlgorithm;
use NetFusion\Core\Wireless\Ris\Enums\ChannelState;
use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Enums\PhaseResolution;
use NetFusion\Core\Wireless\Ris\Exceptions\RisConfigurationException;
use NetFusion\Core\Wireless\Ris\Physics\ChannelMatrix;
use NetFusion\Core\Wireless\Ris\Physics\WaveVector;
use NetFusion\Core\Wireless\Ris\Surface\RisArray;

class RisManager
{
    /**
     * @var array<string, RisArray>
     */
    private array $surfaces = [];

    /**
     * Register or create a RIS metasurface array.
     */
    public function createArray(
        string $surfaceId,
        int $rows = 8,
        int $cols = 8,
        FrequencyBand $band = FrequencyBand::Terahertz,
        PhaseResolution $resolution = PhaseResolution::Continuous,
        ?float $elementSpacing = null
    ): RisArray {
        $array = new RisArray($surfaceId, $rows, $cols, $band, $resolution, $elementSpacing);
        $this->surfaces[$surfaceId] = $array;
        return $array;
    }

    public function getArray(string $surfaceId): RisArray
    {
        if (!isset($this->surfaces[$surfaceId])) {
            throw new RisConfigurationException("RIS array with ID '$surfaceId' is not registered.");
        }
        return $this->surfaces[$surfaceId];
    }

    public function hasArray(string $surfaceId): bool
    {
        return isset($this->surfaces[$surfaceId]);
    }

    /**
     * Steer a surface to reflect an incident direction toward a target direction.
     *
     * @return array{
     *     surface_id: string,
     *     band: string,
     *     elements: int,
     *     in_azimuth_deg: float,
     *     in_elevation_deg: float,
     *     out_azimuth_deg: float,
     *     out_elevation_deg: float,
     *     average_quantization_error_rad: float
     * }
     */
    public function steerBeam(
        string $surfaceId,
        float $inAzimuthDeg,
        float $inElevationDeg,
        float $outAzimuthDeg,
        float $outElevationDeg
    ): array {
        $array = $this->getArray($surfaceId);
        $band = $array->getBand();

        $inWave = new WaveVector($band, deg2rad($inAzimuthDeg), deg2rad($inElevationDeg));
        $outWave = new WaveVector($band, deg2rad($outAzimuthDeg), deg2rad($outElevationDeg));

        $phaseMatrix = SteeringVectorEngine::computeAnomalousReflectionPhase($array, $inWave, $outWave);
        $array->configurePhaseMatrix($phaseMatrix);

        return [
            'surface_id'                     => $surfaceId,
            'band'                           => $band->value,
            'elements'                       => $array->getTotalElements(),
            'in_azimuth_deg'                 => $inAzimuthDeg,
            'in_elevation_deg'               => $inElevationDeg,
            'out_azimuth_deg'                => $outAzimuthDeg,
            'out_elevation_deg'              => $outElevationDeg,
            'average_quantization_error_rad' => round($array->getAverageQuantizationError(), 4),
        ];
    }

    /**
     * Simulate and evaluate end-to-end cascaded link performance with RIS.
     *
     * @return array{
     *     surface_id: string,
     *     carrier_freq_ghz: float,
     *     direct_capacity_bps_hz: float,
     *     effective_capacity_bps_hz: float,
     *     snr_gain_db: float,
     *     quantization_error_rad: float,
     *     total_elements: int,
     *     algorithm: string
     * }
     */
    public function simulateChannelLink(
        string $surfaceId,
        float $txDistanceMeters = 15.0,
        float $rxDistanceMeters = 10.0,
        float $directDistanceMeters = 20.0,
        ChannelState $directChannelState = ChannelState::Obstructed,
        BeamformingAlgorithm $algorithm = BeamformingAlgorithm::MaximumRatio,
        float $txPowerWatts = 1.0
    ): array {
        $array = $this->getArray($surfaceId);
        $band = $array->getBand();

        $txWave = new WaveVector($band, 0.0, deg2rad(30));
        $rxWave = new WaveVector($band, deg2rad(45), deg2rad(15));

        // Synthesize links
        $txToRis = HolographicBeamformer::synthesizeLoSChannel(
            $array,
            $txWave,
            $txDistanceMeters,
            1,
            ChannelState::LineOfSight,
            true
        );

        $risToRx = HolographicBeamformer::synthesizeLoSChannel(
            $array,
            $rxWave,
            $rxDistanceMeters,
            1,
            ChannelState::LineOfSight,
            false
        );

        $directChannel = HolographicBeamformer::synthesizeDirectChannel(
            $band,
            $directDistanceMeters,
            1,
            1,
            $directChannelState
        );

        $metrics = HolographicBeamformer::evaluatePerformance(
            $array,
            $txToRis,
            $risToRx,
            $directChannel,
            $txPowerWatts,
            1e-12,
            $algorithm
        );

        return array_merge([
            'surface_id'       => $surfaceId,
            'carrier_freq_ghz' => round($band->frequencyHz() / 1e9, 2),
        ], $metrics);
    }
}

