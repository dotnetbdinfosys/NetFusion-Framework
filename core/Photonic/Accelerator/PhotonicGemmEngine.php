<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Optical Photonic Computing & Tensor Light-Interferometer
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Photonic\Accelerator;

use NetFusion\Core\Photonic\Enums\LaserWavelength;
use NetFusion\Core\Photonic\Enums\OpticalActivation;
use NetFusion\Core\Photonic\Exceptions\PhotonicException;
use NetFusion\Core\Photonic\Mesh\InterferometerMesh;
use NetFusion\Core\Photonic\Physics\ComplexNumber;

class PhotonicGemmEngine
{
    protected int $dimension;
    protected LaserWavelength $wavelength;
    protected InterferometerMesh $meshU;
    protected InterferometerMesh $meshV;
    /** @var float[] Singular values / attenuation factors for VOAs */
    protected array $sigmaValues = [];
    protected float $scaleFactor = 1.0;

    public function __construct(
        int $dimension = 4,
        LaserWavelength $wavelength = LaserWavelength::C_BAND_1550NM
    ) {
        $this->dimension = $dimension;
        $this->wavelength = $wavelength;
        $this->meshU = new InterferometerMesh("mesh_u", $dimension);
        $this->meshV = new InterferometerMesh("mesh_v", $dimension);
        $this->sigmaValues = array_fill(0, $dimension, 1.0);
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getWavelength(): LaserWavelength
    {
        return $this->wavelength;
    }

    public function getMeshU(): InterferometerMesh
    {
        return $this->meshU;
    }

    public function getMeshV(): InterferometerMesh
    {
        return $this->meshV;
    }

    /**
     * Program optical accelerator with an N x N target weight matrix
     * Uses Singular Value Decomposition (SVD): W = U * Sigma * V^T
     *
     * @param float[][] $weights N x N real weight matrix
     */
    public function loadWeights(array $weights): void
    {
        $n = count($weights);
        if ($n !== $this->dimension) {
            throw new PhotonicException("Weight matrix dimension ({$n}) must match engine dimension ({$this->dimension}).");
        }

        // Two-sided Jacobi SVD for pure PHP
        $svd = self::computeSvd($weights);
        $U = $svd['U'];
        $S = $svd['S'];
        $V = $svd['V'];

        // Normalize singular values to optical transmission [0.0, 1.0]
        $maxSingular = max(1e-12, max($S));
        $this->scaleFactor = $maxSingular;

        $this->sigmaValues = [];
        for ($i = 0; $i < $this->dimension; $i++) {
            $this->sigmaValues[$i] = min(1.0, max(0.0, ($S[$i] ?? 0.0) / $maxSingular));
        }

        // Program Clements meshes to implement unitary matrices U and V^T
        $this->programUnitaryToMesh($this->meshU, $U);
        $this->programUnitaryToMesh($this->meshV, self::matrixTranspose($V));
    }

    /**
     * Approximate mapping of orthogonal matrix into MZI mesh phase shifters
     *
     * @param float[][] $unitary
     */
    protected function programUnitaryToMesh(InterferometerMesh $mesh, array $unitary): void
    {
        $cells = $mesh->getCells();
        $cellCount = count($cells);

        // Compute angle parameters from matrix diagonal & off-diagonal ratios
        $phases = [];
        for ($i = 0; $i < $cellCount; $i++) {
            $row = $i % $this->dimension;
            $col = ($i + 1) % $this->dimension;
            $val = $unitary[$row][$col] ?? 0.0;
            $diag = $unitary[$row][$row] ?? 1.0;

            $theta = atan2(abs($val), abs($diag));
            $phi = ($val < 0) ? M_PI : 0.0;

            $phases[] = ['theta' => $theta, 'phi' => $phi];
        }

        $mesh->setMeshPhases($phases);
    }

    /**
     * Execute coherent Optical Matrix-Vector Multiplication (Optical GEMV): y = W * x
     *
     * @param float[] $inputVector Input real vector x
     * @param OpticalActivation $activation
     * @return array{
     *     output: float[],
     *     latencyPicoseconds: float,
     *     energyFemtojoules: float,
     *     topsPerWatt: float
     * }
     */
    public function forward(
        array $inputVector,
        OpticalActivation $activation = OpticalActivation::LINEAR
    ): array {
        if (count($inputVector) !== $this->dimension) {
            throw new PhotonicException("Input vector size must equal photonic mesh dimension ({$this->dimension}).");
        }

        // 1. Modulate laser carrier with input amplitudes
        $inFields = [];
        foreach ($inputVector as $x) {
            $inFields[] = new ComplexNumber($x, 0.0);
        }

        // 2. First unitary stage: V^T * x
        $stage1 = $this->meshV->propagate($inFields);

        // 3. Attenuation stage: Sigma * (V^T * x)
        $stage2 = [];
        for ($i = 0; $i < $this->dimension; $i++) {
            $stage2[] = $stage1[$i]->mul($this->sigmaValues[$i]);
        }

        // 4. Second unitary stage: U * Sigma * V^T * x
        $stage3 = $this->meshU->propagate($stage2);

        // 5. Photodiode detection & scale recovery
        $detected = [];
        for ($i = 0; $i < $this->dimension; $i++) {
            // In coherent homodyne detection, real field component is detected
            $val = $stage3[$i]->real() * $this->scaleFactor;

            // Apply non-linear optical activation
            $activated = match ($activation) {
                OpticalActivation::LINEAR => $val,
                OpticalActivation::ELECTRO_OPTIC_RELU => max(0.0, $val),
                OpticalActivation::OPTICAL_SIGMOID => 1.0 / (1.0 + exp(-min(50.0, max(-50.0, $val)))),
                OpticalActivation::SATURABLE_ABSORBER => $val / (1.0 + abs($val) / 10.0),
            };

            $detected[] = round($activated, 4);
        }

        // Performance & Energy Analytics
        // Silicon photonics waveguide group index n_g ~ 4.0; 50 um per cell * 2*N cells
        $chipLengthMm = ($this->dimension * 2 * 0.05); // mm
        $latencyPs = round(($chipLengthMm * 1e-3 / 2.99792e8) * 4.0 * 1e12 + 10.0, 2); // ~15-30 ps

        // Laser power + electro-optic DAC/ADC: ~8.5 fJ per MAC operation
        $macOps = $this->dimension * $this->dimension;
        $energyFj = round($macOps * 8.5, 2);
        // Compute equivalent TOPS/Watt: (2 * MACs) / (energy in J) = (2 * 1e15) / (8.5 * 1e15) ~ 235 TOPS/W
        $topsPerWatt = round((2.0 / 8.5) * 1000.0, 1);

        return [
            'output' => $detected,
            'latencyPicoseconds' => $latencyPs,
            'energyFemtojoules' => $energyFj,
            'topsPerWatt' => $topsPerWatt,
        ];
    }

    /**
     * Pure PHP Two-Sided Jacobi Singular Value Decomposition (SVD)
     * Decomposes real matrix A = U * S * V^T
     *
     * @param float[][] $A N x N matrix
     * @return array{U: float[][], S: float[], V: float[][]}
     */
    public static function computeSvd(array $A, int $maxIters = 50, float $tol = 1e-10): array
    {
        $n = count($A);
        // Initialize U and V as identity matrices
        $U = array_fill(0, $n, array_fill(0, $n, 0.0));
        $V = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            $U[$i][$i] = 1.0;
            $V[$i][$i] = 1.0;
        }

        $B = $A; // Copy of A

        for ($iter = 0; $iter < $maxIters; $iter++) {
            $maxOff = 0.0;

            for ($p = 0; $p < $n - 1; $p++) {
                for ($q = $p + 1; $q < $n; $q++) {
                    // Compute 2x2 submatrix of B^T * B
                    $colP_dot_P = 0.0;
                    $colQ_dot_Q = 0.0;
                    $colP_dot_Q = 0.0;

                    for ($k = 0; $k < $n; $k++) {
                        $colP_dot_P += $B[$k][$p] * $B[$k][$p];
                        $colQ_dot_Q += $B[$k][$q] * $B[$k][$q];
                        $colP_dot_Q += $B[$k][$p] * $B[$k][$q];
                    }

                    $absOff = abs($colP_dot_Q);
                    if ($absOff > $maxOff) {
                        $maxOff = $absOff;
                    }

                    if ($absOff < $tol) {
                        continue;
                    }

                    // Jacobi rotation angle for V
                    $tau = ($colQ_dot_Q - $colP_dot_P) / (2.0 * $colP_dot_Q);
                    $t = ($tau >= 0) ? (1.0 / ($tau + sqrt(1.0 + $tau * $tau))) : (-1.0 / (-$tau + sqrt(1.0 + $tau * $tau)));
                    $c = 1.0 / sqrt(1.0 + $t * $t);
                    $s = $t * $c;

                    // Rotate columns p and q of B and V
                    for ($k = 0; $k < $n; $k++) {
                        $bp = $B[$k][$p];
                        $bq = $B[$k][$q];
                        $B[$k][$p] = $c * $bp - $s * $bq;
                        $B[$k][$q] = $s * $bp + $c * $bq;

                        $vp = $V[$k][$p];
                        $vq = $V[$k][$q];
                        $V[$k][$p] = $c * $vp - $s * $vq;
                        $V[$k][$q] = $s * $vp + $c * $vq;
                    }
                }
            }

            if ($maxOff < $tol) {
                break;
            }
        }

        // Singular values are Euclidean norms of columns of B
        $S = [];
        for ($j = 0; $j < $n; $j++) {
            $norm = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $norm += $B[$i][$j] * $B[$i][$j];
            }
            $singular = sqrt($norm);
            $S[$j] = $singular;

            // Columns of U are B[:, j] / S[j]
            for ($i = 0; $i < $n; $i++) {
                $U[$i][$j] = ($singular > 1e-12) ? ($B[$i][$j] / $singular) : 0.0;
            }
        }

        // Sort singular values in descending order
        $order = range(0, $n - 1);
        usort($order, fn($a, $b) => $S[$b] <=> $S[$a]);

        $sortedS = [];
        $sortedU = array_fill(0, $n, array_fill(0, $n, 0.0));
        $sortedV = array_fill(0, $n, array_fill(0, $n, 0.0));

        for ($newIdx = 0; $newIdx < $n; $newIdx++) {
            $oldIdx = $order[$newIdx];
            $sortedS[$newIdx] = $S[$oldIdx];
            for ($i = 0; $i < $n; $i++) {
                $sortedU[$i][$newIdx] = $U[$i][$oldIdx];
                $sortedV[$i][$newIdx] = $V[$i][$oldIdx];
            }
        }

        return ['U' => $sortedU, 'S' => $sortedS, 'V' => $sortedV];
    }

    protected static function matrixTranspose(array $M): array
    {
        $rows = count($M);
        $cols = count($M[0]);
        $out = array_fill(0, $cols, array_fill(0, $rows, 0.0));
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $out[$j][$i] = $M[$i][$j];
            }
        }
        return $out;
    }
}
