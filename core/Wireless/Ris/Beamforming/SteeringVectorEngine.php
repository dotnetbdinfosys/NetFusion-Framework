<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Array response and steering vector engine for 3D spatial beam steering.
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
use NetFusion\Core\Wireless\Ris\Physics\WaveVector;
use NetFusion\Core\Wireless\Ris\Surface\RisArray;

class SteeringVectorEngine
{
    /**
     * Compute array steering response vector a(theta, phi) of length N = M * K.
     * For element (r, c) at (x, y), phase is -(kx * x + ky * y).
     *
     * @return array<int, ComplexNumber>
     */
    public static function computeSteeringVector(RisArray $array, WaveVector $waveVector): array
    {
        $steering = [];
        $rows = $array->getRows();
        $cols = $array->getCols();

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $coords = $array->getPhysicalCoordinates($r, $c);
                $phase = $waveVector->phaseAtPlane($coords['x'], $coords['y']);
                // Unit amplitude, complex exponential e^(j * phase)
                $steering[] = ComplexNumber::fromPolar(1.0, $phase);
            }
        }

        return $steering;
    }

    /**
     * Compute phase profile required on RIS array to reflect an incident beam (inWave)
     * toward a desired reflected beam (outWave).
     * Condition: phi_RIS(x, y) = phase_out(x, y) - phase_in(x, y) (modulo 2pi).
     *
     * @return array<int, array<int, float>> [rows][cols] phase angles in radians
     */
    public static function computeAnomalousReflectionPhase(
        RisArray $array,
        WaveVector $incidentWave,
        WaveVector $desiredWave
    ): array {
        $rows = $array->getRows();
        $cols = $array->getCols();
        $phaseMatrix = [];

        for ($r = 0; $r < $rows; $r++) {
            $rowPhases = [];
            for ($c = 0; $c < $cols; $c++) {
                $coords = $array->getPhysicalCoordinates($r, $c);
                $inPhase = $incidentWave->phaseAtPlane($coords['x'], $coords['y']);
                $outPhase = $desiredWave->phaseAtPlane($coords['x'], $coords['y']);

                // Phase compensation required at element: phi = outPhase - inPhase
                $phaseDiff = $outPhase - $inPhase;
                $rowPhases[] = $phaseDiff;
            }
            $phaseMatrix[] = $rowPhases;
        }

        return $phaseMatrix;
    }

    /**
     * Compute array factor / directivity gain in linear power for a given target direction.
     * AF = |sum a_n(direction) * exp(j * phi_n)|^2 / N
     *
     * @param array<int, ComplexNumber> $elementResponses
     */
    public static function calculateArrayGain(
        RisArray $array,
        WaveVector $targetDirection,
        array $elementResponses
    ): float {
        $steering = self::computeSteeringVector($array, $targetDirection);
        $totalElements = $array->getTotalElements();

        $sum = ComplexNumber::zero();
        for ($i = 0; $i < $totalElements; $i++) {
            // Received component = element_response * conjugate(steering_vector_component)
            $term = $elementResponses[$i]->multiply($steering[$i]->conjugate());
            $sum = $sum->add($term);
        }

        $power = $sum->magnitude() * $sum->magnitude();
        return $power / max(1, $totalElements);
    }
}

