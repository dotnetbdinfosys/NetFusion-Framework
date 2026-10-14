<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Brain-Computer Interface (BCI) & Neural Signal Decoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Neural\Bci\Spatial;

use NetFusion\Core\Neural\Bci\Exceptions\BciSignalException;

class CommonSpatialPattern
{
    /** @var float[][] Spatial filter projection matrix W */
    protected array $filters = [];
    protected int $numFilterPairs;

    public function __construct(int $numFilterPairs = 2)
    {
        $this->numFilterPairs = $numFilterPairs;
    }

    /**
     * Compute normalized covariance matrix for a multi-channel trial
     * Matrix dimensions: Channels x TimeSamples
     *
     * @param float[][] $trial [channelIndex][sampleIndex]
     * @return float[][] [channelIndex][channelIndex]
     */
    public static function computeCovariance(array $trial): array
    {
        $numChannels = count($trial);
        if ($numChannels === 0) {
            return [];
        }

        $numSamples = count($trial[0]);
        $cov = array_fill(0, $numChannels, array_fill(0, $numChannels, 0.0));

        // Center channels
        $centered = [];
        for ($i = 0; $i < $numChannels; $i++) {
            $mean = array_sum($trial[$i]) / max(1, $numSamples);
            $centered[$i] = [];
            for ($t = 0; $t < $numSamples; $t++) {
                $centered[$i][$t] = $trial[$i][$t] - $mean;
            }
        }

        // Covariance = X * X^T
        $trace = 0.0;
        for ($i = 0; $i < $numChannels; $i++) {
            for ($j = $i; $j < $numChannels; $j++) {
                $dot = 0.0;
                for ($t = 0; $t < $numSamples; $t++) {
                    $dot += $centered[$i][$t] * $centered[$j][$t];
                }
                $cov[$i][$j] = $dot;
                $cov[$j][$i] = $dot;
            }
            $trace += $cov[$i][$i];
        }

        // Normalize by trace to ensure scaling invariance
        $trace = max(1e-12, $trace);
        for ($i = 0; $i < $numChannels; $i++) {
            for ($j = 0; $j < $numChannels; $j++) {
                $cov[$i][$j] /= $trace;
            }
        }

        return $cov;
    }

    /**
     * Average covariance across multiple trials of the same class
     *
     * @param float[][][] $trials List of trials [trialIndex][channelIndex][sampleIndex]
     * @return float[][]
     */
    public static function averageCovariance(array $trials): array
    {
        if (empty($trials)) {
            throw new BciSignalException("No trials provided to compute covariance.");
        }

        $firstCov = self::computeCovariance($trials[0]);
        $dim = count($firstCov);
        $meanCov = array_fill(0, $dim, array_fill(0, $dim, 0.0));

        foreach ($trials as $trial) {
            $c = self::computeCovariance($trial);
            for ($i = 0; $i < $dim; $i++) {
                for ($j = 0; $j < $dim; $j++) {
                    $meanCov[$i][$j] += $c[$i][$j];
                }
            }
        }

        $count = count($trials);
        for ($i = 0; $i < $dim; $i++) {
            for ($j = 0; $j < $dim; $j++) {
                $meanCov[$i][$j] /= $count;
            }
        }

        return $meanCov;
    }

    /**
     * Jacobi Eigenvalue Algorithm for Real Symmetric Matrix
     * Returns eigenvalues and eigenvectors matrix V
     *
     * @param float[][] $A Symmetric matrix N x N
     * @return array{values: float[], vectors: float[][]}
     */
    public static function jacobiEigen(array $A, int $maxIterations = 100, float $tolerance = 1e-10): array
    {
        $n = count($A);
        $V = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            $V[$i][$i] = 1.0;
        }

        for ($iter = 0; $iter < $maxIterations; $iter++) {
            // Find maximum off-diagonal element
            $maxOff = 0.0;
            $p = 0;
            $q = 1;

            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $absVal = abs($A[$i][$j]);
                    if ($absVal > $maxOff) {
                        $maxOff = $absVal;
                        $p = $i;
                        $q = $j;
                    }
                }
            }

            if ($maxOff < $tolerance) {
                break;
            }

            // Compute Jacobi rotation
            $app = $A[$p][$p];
            $aqq = $A[$q][$q];
            $apq = $A[$p][$q];

            $theta = ($aqq - $app) / (2.0 * $apq);
            $t = ($theta >= 0) ? (1.0 / ($theta + sqrt($theta * $theta + 1.0))) : (-1.0 / (-$theta + sqrt($theta * $theta + 1.0)));
            $c = 1.0 / sqrt(1.0 + $t * $t);
            $s = $t * $c;

            // Apply rotation to A
            $A[$p][$p] = $app - $t * $apq;
            $A[$q][$q] = $aqq + $t * $apq;
            $A[$p][$q] = 0.0;
            $A[$q][$p] = 0.0;

            for ($i = 0; $i < $n; $i++) {
                if ($i !== $p && $i !== $q) {
                    $aip = $A[$i][$p];
                    $aiq = $A[$i][$q];
                    $A[$i][$p] = $c * $aip - $s * $aiq;
                    $A[$p][$i] = $A[$i][$p];
                    $A[$i][$q] = $s * $aip + $c * $aiq;
                    $A[$q][$i] = $A[$i][$q];
                }

                // Accumulate eigenvectors in V
                $vip = $V[$i][$p];
                $viq = $V[$i][$q];
                $V[$i][$p] = $c * $vip - $s * $viq;
                $V[$i][$q] = $s * $vip + $c * $viq;
            }
        }

        $eigenvalues = [];
        for ($i = 0; $i < $n; $i++) {
            $eigenvalues[] = $A[$i][$i];
        }

        return ['values' => $eigenvalues, 'vectors' => $V];
    }

    /**
     * Train Common Spatial Patterns (CSP) filters from two-class EEG trials
     *
     * @param float[][][] $class1Trials [trial][channel][sample]
     * @param float[][][] $class2Trials [trial][channel][sample]
     */
    public function fit(array $class1Trials, array $class2Trials): void
    {
        $cov1 = self::averageCovariance($class1Trials);
        $cov2 = self::averageCovariance($class2Trials);

        $n = count($cov1);
        $composite = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $composite[$i][$j] = $cov1[$i][$j] + $cov2[$i][$j];
            }
        }

        // Composite eigendecomposition: C_c = U_c * Lambda_c * U_c^T
        $eigComp = self::jacobiEigen($composite);
        $valC = $eigComp['values'];
        $vecC = $eigComp['vectors'];

        // Whitening transformation matrix P = Lambda_c^(-1/2) * U_c^T
        $P = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            $scale = 1.0 / sqrt(max(1e-10, abs($valC[$i])));
            for ($j = 0; $j < $n; $j++) {
                $P[$i][$j] = $vecC[$j][$i] * $scale;
            }
        }

        // Transformed S1 = P * cov1 * P^T
        $S1 = self::matrixMul(self::matrixMul($P, $cov1), self::matrixTranspose($P));

        // Diagonalize S1: B * Lambda_1 * B^T
        $eigS1 = self::jacobiEigen($S1);
        $valS1 = $eigS1['values'];
        $vecS1 = $eigS1['vectors'];

        // Sort eigenvectors by eigenvalues in descending order
        $indices = range(0, $n - 1);
        usort($indices, fn($a, $b) => $valS1[$b] <=> $valS1[$a]);

        // Full projection matrix W = B^T * P
        $B = $vecS1;
        $BT = self::matrixTranspose($B);
        $W = self::matrixMul($BT, $P);

        // Select top m and bottom m filters
        $m = min($this->numFilterPairs, intdiv($n, 2));
        if ($m <= 0) {
            $m = 1;
        }

        $selectedFilters = [];
        for ($i = 0; $i < $m; $i++) {
            $selectedFilters[] = $W[$indices[$i]];
        }
        for ($i = $n - $m; $i < $n; $i++) {
            $selectedFilters[] = $W[$indices[$i]];
        }

        $this->filters = $selectedFilters;
    }

    /**
     * Transform multi-channel trial into CSP spatial log-variance feature vector
     *
     * @param float[][] $trial Channels x TimeSamples
     * @return float[] Feature vector
     */
    public function transform(array $trial): array
    {
        if (empty($this->filters)) {
            throw new BciSignalException("CSP model is not fitted yet.");
        }

        $numFilters = count($this->filters);
        $numSamples = count($trial[0]);
        $features = [];
        $totalVar = 0.0;

        for ($f = 0; $f < $numFilters; $f++) {
            $filterRow = $this->filters[$f];
            // Filtered signal z[t] = w^T * x[:, t]
            $z = [];
            for ($t = 0; $t < $numSamples; $t++) {
                $val = 0.0;
                for ($ch = 0; $ch < count($filterRow); $ch++) {
                    $val += $filterRow[$ch] * ($trial[$ch][$t] ?? 0.0);
                }
                $z[] = $val;
            }

            // Variance of z
            $mean = array_sum($z) / max(1, $numSamples);
            $var = 0.0;
            foreach ($z as $v) {
                $diff = $v - $mean;
                $var += $diff * $diff;
            }
            $var = max(1e-12, $var / max(1, $numSamples));
            $features[] = $var;
            $totalVar += $var;
        }

        // Log-variance normalized features
        $totalVar = max(1e-12, $totalVar);
        $logFeatures = [];
        foreach ($features as $var) {
            $logFeatures[] = log($var / $totalVar);
        }

        return $logFeatures;
    }

    public function getFilters(): array
    {
        return $this->filters;
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

    protected static function matrixMul(array $A, array $B): array
    {
        $rowsA = count($A);
        $colsA = count($A[0]);
        $colsB = count($B[0]);

        $C = array_fill(0, $rowsA, array_fill(0, $colsB, 0.0));
        for ($i = 0; $i < $rowsA; $i++) {
            for ($j = 0; $j < $colsB; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += $A[$i][$k] * $B[$k][$j];
                }
                $C[$i][$j] = $sum;
            }
        }
        return $C;
    }
}
