<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Single tunable metamaterial unit cell element.
 *
 * @package    NetFusion\Wireless\Ris\Surface
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Surface;

use NetFusion\Core\Photonic\Physics\ComplexNumber;
use NetFusion\Core\Wireless\Ris\Enums\PhaseResolution;
use NetFusion\Core\Wireless\Ris\Exceptions\RisConfigurationException;

class MetamaterialElement
{
    private float $phaseRad;      // Quantized phase shift in [0, 2pi)
    private float $idealPhaseRad; // Ideal unquantized phase shift
    private float $amplitude;     // Reflection coefficient [0.0, 1.0]

    public function __construct(
        private readonly int $rowIndex,
        private readonly int $colIndex,
        float $phaseRad = 0.0,
        float $amplitude = 1.0,
        private readonly PhaseResolution $resolution = PhaseResolution::Continuous
    ) {
        if ($amplitude < 0.0 || $amplitude > 1.0) {
            throw new RisConfigurationException('Metamaterial reflection amplitude must be between 0.0 and 1.0.');
        }

        $this->amplitude = $amplitude;
        $this->setPhase($phaseRad);
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function getColIndex(): int
    {
        return $this->colIndex;
    }

    public function getResolution(): PhaseResolution
    {
        return $this->resolution;
    }

    public function getPhase(): float
    {
        return $this->phaseRad;
    }

    public function getIdealPhase(): float
    {
        return $this->idealPhaseRad;
    }

    public function getAmplitude(): float
    {
        return $this->amplitude;
    }

    public function setPhase(float $phaseRad): void
    {
        $twoPi = 2.0 * M_PI;
        $norm = fmod($phaseRad, $twoPi);
        if ($norm < 0) {
            $norm += $twoPi;
        }

        $this->idealPhaseRad = $norm;
        $this->phaseRad = $this->resolution->quantize($norm);
    }

    public function setAmplitude(float $amplitude): void
    {
        if ($amplitude < 0.0 || $amplitude > 1.0) {
            throw new RisConfigurationException('Reflection amplitude must be between 0.0 and 1.0.');
        }
        $this->amplitude = $amplitude;
    }

    /**
     * Get complex reflection coefficient: beta * e^(j * theta)
     */
    public function getReflectionCoefficient(): ComplexNumber
    {
        return ComplexNumber::fromPolar($this->amplitude, $this->phaseRad);
    }

    /**
     * Quantization error in radians: |ideal - quantized|
     */
    public function getQuantizationPhaseError(): float
    {
        $diff = abs($this->idealPhaseRad - $this->phaseRad);
        if ($diff > M_PI) {
            $diff = (2.0 * M_PI) - $diff;
        }
        return $diff;
    }
}

