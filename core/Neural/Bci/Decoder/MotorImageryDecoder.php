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

namespace NetFusion\Core\Neural\Bci\Decoder;

use NetFusion\Core\Neural\Bci\Dsp\DspFilter;
use NetFusion\Core\Neural\Bci\Spatial\CommonSpatialPattern;
use NetFusion\Core\Neural\Bci\Exceptions\BciSignalException;

class MotorImageryDecoder
{
    protected CommonSpatialPattern $csp;
    protected float $samplingRate;
    protected float $lowCut;
    protected float $highCut;
    protected string $class1Label;
    protected string $class2Label;

    /** @var float[] LDA weight vector */
    protected array $ldaWeights = [];
    protected float $ldaBias = 0.0;
    protected bool $isTrained = false;

    public function __construct(
        float $samplingRate = 250.0,
        float $lowCut = 8.0,
        float $highCut = 30.0,
        string $class1Label = 'left_hand',
        string $class2Label = 'right_hand',
        int $filterPairs = 2
    ) {
        $this->samplingRate = $samplingRate;
        $this->lowCut = $lowCut;
        $this->highCut = $highCut;
        $this->class1Label = $class1Label;
        $this->class2Label = $class2Label;
        $this->csp = new CommonSpatialPattern($filterPairs);
    }

    /**
     * Preprocess trial by applying bandpass filter to all channels
     *
     * @param float[][] $trial Channels x Samples
     * @return float[][]
     */
    public function preprocessTrial(array $trial): array
    {
        $coeff = DspFilter::designBandpass($this->lowCut, $this->highCut, $this->samplingRate);
        $filtered = [];
        foreach ($trial as $channelData) {
            $filtered[] = DspFilter::filtfilt($channelData, $coeff);
        }
        return $filtered;
    }

    /**
     * Train CSP + LDA classifier on labeled motor imagery trials
     *
     * @param float[][][] $class1Trials [trial][channel][sample]
     * @param float[][][] $class2Trials [trial][channel][sample]
     */
    public function train(array $class1Trials, array $class2Trials): void
    {
        if (empty($class1Trials) || empty($class2Trials)) {
            throw new BciSignalException("Both classes must contain training trials.");
        }

        // 1. Preprocess all trials in Mu/Beta rhythm (8-30 Hz)
        $prep1 = [];
        foreach ($class1Trials as $t) {
            $prep1[] = $this->preprocessTrial($t);
        }

        $prep2 = [];
        foreach ($class2Trials as $t) {
            $prep2[] = $this->preprocessTrial($t);
        }

        // 2. Train CSP spatial filters
        $this->csp->fit($prep1, $prep2);

        // 3. Extract CSP features
        $feat1 = [];
        foreach ($prep1 as $t) {
            $feat1[] = $this->csp->transform($t);
        }

        $feat2 = [];
        foreach ($prep2 as $t) {
            $feat2[] = $this->csp->transform($t);
        }

        // 4. Train Linear Discriminant Analysis (LDA)
        $nDim = count($feat1[0]);
        $mu1 = array_fill(0, $nDim, 0.0);
        $mu2 = array_fill(0, $nDim, 0.0);

        foreach ($feat1 as $f) {
            for ($i = 0; $i < $nDim; $i++) {
                $mu1[$i] += $f[$i];
            }
        }
        for ($i = 0; $i < $nDim; $i++) {
            $mu1[$i] /= count($feat1);
        }

        foreach ($feat2 as $f) {
            for ($i = 0; $i < $nDim; $i++) {
                $mu2[$i] += $f[$i];
            }
        }
        for ($i = 0; $i < $nDim; $i++) {
            $mu2[$i] /= count($feat2);
        }

        // Pooled within-class covariance matrix
        $pooledCov = array_fill(0, $nDim, array_fill(0, $nDim, 0.0));
        foreach ($feat1 as $f) {
            for ($i = 0; $i < $nDim; $i++) {
                for ($j = 0; $j < $nDim; $j++) {
                    $pooledCov[$i][$j] += ($f[$i] - $mu1[$i]) * ($f[$j] - $mu1[$j]);
                }
            }
        }
        foreach ($feat2 as $f) {
            for ($i = 0; $i < $nDim; $i++) {
                for ($j = 0; $j < $nDim; $j++) {
                    $pooledCov[$i][$j] += ($f[$i] - $mu2[$i]) * ($f[$j] - $mu2[$j]);
                }
            }
        }

        $totalSamples = count($feat1) + count($feat2);
        for ($i = 0; $i < $nDim; $i++) {
            for ($j = 0; $j < $nDim; $j++) {
                $pooledCov[$i][$j] /= max(1, $totalSamples - 2);
            }
            // Regularization diagonal loading (shrinkage)
            $pooledCov[$i][$i] += 1e-4;
        }

        // Inverse of pooled covariance (for 2x2 to 4x4 dimensions using Gauss-Jordan)
        $invCov = self::invertMatrix($pooledCov);

        // LDA weights w = Sigma^-1 * (mu1 - mu2)
        $weights = [];
        for ($i = 0; $i < $nDim; $i++) {
            $w = 0.0;
            for ($j = 0; $j < $nDim; $j++) {
                $w += $invCov[$i][$j] * ($mu1[$j] - $mu2[$j]);
            }
            $weights[] = $w;
        }

        // Bias b = -w^T * (mu1 + mu2) / 2
        $bias = 0.0;
        for ($i = 0; $i < $nDim; $i++) {
            $bias += $weights[$i] * (($mu1[$i] + $mu2[$i]) / 2.0);
        }
        $bias = -$bias;

        $this->ldaWeights = $weights;
        $this->ldaBias = $bias;
        $this->isTrained = true;
    }

    /**
     * Predict motor imagery intent for an incoming test trial
     *
     * @param float[][] $trial Channels x TimeSamples
     * @return array{intent: string, confidence: float, score: float, features: float[]}
     */
    public function predict(array $trial): array
    {
        if (!$this->isTrained) {
            throw new BciSignalException("Decoder is not trained. Call train() first.");
        }

        $prep = $this->preprocessTrial($trial);
        $features = $this->csp->transform($prep);

        // Linear score: y = w^T * x + b
        $score = $this->ldaBias;
        for ($i = 0; $i < count($this->ldaWeights); $i++) {
            $score += $this->ldaWeights[$i] * $features[$i];
        }

        // Sigmoid probability for class 1
        $probClass1 = 1.0 / (1.0 + exp(-min(50.0, max(-50.0, $score))));

        if ($score >= 0) {
            $intent = $this->class1Label;
            $confidence = $probClass1;
        } else {
            $intent = $this->class2Label;
            $confidence = 1.0 - $probClass1;
        }

        return [
            'intent' => $intent,
            'confidence' => round($confidence, 4),
            'score' => round($score, 4),
            'features' => $features,
        ];
    }

    public function isTrained(): bool
    {
        return $this->isTrained;
    }

    /**
     * Matrix inversion using Gauss-Jordan elimination with partial pivoting
     *
     * @param float[][] $matrix
     * @return float[][]
     */
    protected static function invertMatrix(array $matrix): array
    {
        $n = count($matrix);
        $augmented = [];
        for ($i = 0; $i < $n; $i++) {
            $augmented[$i] = array_merge($matrix[$i], array_fill(0, $n, 0.0));
            $augmented[$i][$n + $i] = 1.0;
        }

        for ($i = 0; $i < $n; $i++) {
            // Pivot search
            $maxRow = $i;
            for ($k = $i + 1; $k < $n; $k++) {
                if (abs($augmented[$k][$i]) > abs($augmented[$maxRow][$i])) {
                    $maxRow = $k;
                }
            }

            $temp = $augmented[$i];
            $augmented[$i] = $augmented[$maxRow];
            $augmented[$maxRow] = $temp;

            $pivot = $augmented[$i][$i];
            if (abs($pivot) < 1e-12) {
                $pivot = 1e-12;
            }

            for ($j = 0; $j < 2 * $n; $j++) {
                $augmented[$i][$j] /= $pivot;
            }

            for ($k = 0; $k < $n; $k++) {
                if ($k !== $i) {
                    $factor = $augmented[$k][$i];
                    for ($j = 0; $j < 2 * $n; $j++) {
                        $augmented[$k][$j] -= $factor * $augmented[$i][$j];
                    }
                }
            }
        }

        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            $inv[$i] = array_slice($augmented[$i], $n);
        }

        return $inv;
    }
}
