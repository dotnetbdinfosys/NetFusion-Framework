<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * 2D Planar Reconfigurable Intelligent Surface (RIS) Metamaterial Array.
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
use NetFusion\Core\Wireless\Ris\Enums\FrequencyBand;
use NetFusion\Core\Wireless\Ris\Enums\PhaseResolution;
use NetFusion\Core\Wireless\Ris\Exceptions\RisConfigurationException;
use NetFusion\Core\Wireless\Ris\Physics\ChannelMatrix;

class RisArray
{
    /**
     * @var array<int, array<int, MetamaterialElement>> [rows][cols]
     */
    private array $elements = [];
    private float $dx; // Element spacing along X (meters)
    private float $dy; // Element spacing along Y (meters)

    public function __construct(
        private readonly string $surfaceId,
        private readonly int $rows,
        private readonly int $cols,
        private readonly FrequencyBand $band = FrequencyBand::Terahertz,
        private readonly PhaseResolution $resolution = PhaseResolution::Continuous,
        ?float $elementSpacingMeters = null
    ) {
        if ($this->rows <= 0 || $this->cols <= 0) {
            throw new RisConfigurationException('RIS array dimensions must be positive integers.');
        }

        // Standard half-wavelength spacing (lambda / 2) if not specified
        $spacing = $elementSpacingMeters ?? ($this->band->wavelengthMeters() / 2.0);
        $this->dx = $spacing;
        $this->dy = $spacing;

        // Initialize elements
        for ($r = 0; $r < $this->rows; $r++) {
            $rowElements = [];
            for ($c = 0; $c < $this->cols; $c++) {
                $rowElements[] = new MetamaterialElement($r, $c, 0.0, 1.0, $this->resolution);
            }
            $this->elements[] = $rowElements;
        }
    }

    public function getSurfaceId(): string
    {
        return $this->surfaceId;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getCols(): int
    {
        return $this->cols;
    }

    public function getTotalElements(): int
    {
        return $this->rows * $this->cols;
    }

    public function getBand(): FrequencyBand
    {
        return $this->band;
    }

    public function getResolution(): PhaseResolution
    {
        return $this->resolution;
    }

    public function getSpacingX(): float
    {
        return $this->dx;
    }

    public function getSpacingY(): float
    {
        return $this->dy;
    }

    public function getElement(int $row, int $col): MetamaterialElement
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) {
            throw new RisConfigurationException("Element coordinates [$row, $col] out of bounds.");
        }
        return $this->elements[$row][$col];
    }

    /**
     * Set phase shift across the entire array or specific element.
     */
    public function setElementPhase(int $row, int $col, float $phaseRad): void
    {
        $this->getElement($row, $col)->setPhase($phaseRad);
    }

    /**
     * Apply a 2D phase shift matrix.
     * @param array<int, array<int, float>> $phaseMatrix
     */
    public function configurePhaseMatrix(array $phaseMatrix): void
    {
        if (count($phaseMatrix) !== $this->rows) {
            throw new RisConfigurationException("Phase matrix rows do not match array rows ({$this->rows}).");
        }

        for ($r = 0; $r < $this->rows; $r++) {
            if (count($phaseMatrix[$r]) !== $this->cols) {
                throw new RisConfigurationException("Phase matrix column count mismatch at row $r.");
            }
            for ($c = 0; $c < $this->cols; $c++) {
                $this->elements[$r][$c]->setPhase($phaseMatrix[$r][$c]);
            }
        }
    }

    /**
     * Flatten elements into a 1D diagonal reflection matrix Phi of size N x N (where N = rows * cols).
     */
    public function getDiagonalReflectionMatrix(): ChannelMatrix
    {
        $n = $this->getTotalElements();
        $matrix = ChannelMatrix::zeros($n, $n);

        $idx = 0;
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                $refCoeff = $this->elements[$r][$c]->getReflectionCoefficient();
                $matrix->set($idx, $idx, $refCoeff);
                $idx++;
            }
        }

        return $matrix;
    }

    /**
     * Get 1D complex reflection vector of length N = rows * cols.
     * @return array<int, ComplexNumber>
     */
    public function getReflectionVector(): array
    {
        $vector = [];
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                $vector[] = $this->elements[$r][$c]->getReflectionCoefficient();
            }
        }
        return $vector;
    }

    /**
     * Calculate physical coordinates (x, y) of element (r, c) on the metasurface plane.
     * @return array{x: float, y: float}
     */
    public function getPhysicalCoordinates(int $row, int $col): array
    {
        // Center the array around (0,0)
        $x = ($col - ($this->cols - 1) / 2.0) * $this->dx;
        $y = ($row - ($this->rows - 1) / 2.0) * $this->dy;
        return ['x' => $x, 'y' => $y];
    }

    /**
     * Average quantization phase error across all elements.
     */
    public function getAverageQuantizationError(): float
    {
        $sum = 0.0;
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                $sum += $this->elements[$r][$c]->getQuantizationPhaseError();
            }
        }
        return $sum / $this->getTotalElements();
    }
}

