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

namespace NetFusion\Core\Neural\Bci\Dsp;

class DspFilter
{
    /**
     * Compute biquad bandpass filter coefficients (Audio EQ Cookbook / Butterworth)
     *
     * @param float $lowCut Low cutoff frequency in Hz
     * @param float $highCut High cutoff frequency in Hz
     * @param float $samplingRate Sampling frequency in Hz (e.g. 250.0)
     * @return array{b0: float, b1: float, b2: float, a1: float, a2: float}
     */
    public static function designBandpass(float $lowCut, float $highCut, float $samplingRate): array
    {
        $centerFreq = sqrt($lowCut * $highCut);
        $bandwidth = max(0.1, $highCut - $lowCut);
        $w0 = 2.0 * M_PI * $centerFreq / $samplingRate;
        $q = $centerFreq / $bandwidth;
        $alpha = sin($w0) / (2.0 * $q);

        $b0 = $alpha;
        $b1 = 0.0;
        $b2 = -$alpha;
        $a0 = 1.0 + $alpha;
        $a1 = -2.0 * cos($w0);
        $a2 = 1.0 - $alpha;

        return [
            'b0' => $b0 / $a0,
            'b1' => $b1 / $a0,
            'b2' => $b2 / $a0,
            'a1' => $a1 / $a0,
            'a2' => $a2 / $a0,
        ];
    }

    /**
     * Compute biquad notch filter coefficients (e.g. 50Hz or 60Hz power-line hum removal)
     *
     * @return array{b0: float, b1: float, b2: float, a1: float, a2: float}
     */
    public static function designNotch(float $notchFreq, float $samplingRate, float $q = 30.0): array
    {
        $w0 = 2.0 * M_PI * $notchFreq / $samplingRate;
        $alpha = sin($w0) / (2.0 * $q);

        $b0 = 1.0;
        $b1 = -2.0 * cos($w0);
        $b2 = 1.0;
        $a0 = 1.0 + $alpha;
        $a1 = -2.0 * cos($w0);
        $a2 = 1.0 - $alpha;

        return [
            'b0' => $b0 / $a0,
            'b1' => $b1 / $a0,
            'b2' => $b2 / $a0,
            'a1' => $a1 / $a0,
            'a2' => $a2 / $a0,
        ];
    }

    /**
     * Apply IIR biquad filter (Direct Form II Transposed) forward
     *
     * @param float[] $signal
     * @param array{b0: float, b1: float, b2: float, a1: float, a2: float} $coeff
     * @return float[]
     */
    public static function applyBiquad(array $signal, array $coeff): array
    {
        $b0 = $coeff['b0'];
        $b1 = $coeff['b1'];
        $b2 = $coeff['b2'];
        $a1 = $coeff['a1'];
        $a2 = $coeff['a2'];

        $out = [];
        $d1 = 0.0;
        $d2 = 0.0;

        foreach ($signal as $x) {
            $y = $b0 * $x + $d1;
            $d1 = $b1 * $x - $a1 * $y + $d2;
            $d2 = $b2 * $x - $a2 * $y;
            $out[] = $y;
        }

        return $out;
    }

    /**
     * Zero-phase forward-backward digital filter (filtfilt)
     *
     * @param float[] $signal
     * @param array{b0: float, b1: float, b2: float, a1: float, a2: float} $coeff
     * @return float[]
     */
    public static function filtfilt(array $signal, array $coeff): array
    {
        if (empty($signal)) {
            return [];
        }

        $forward = self::applyBiquad($signal, $coeff);
        $reversed = array_reverse($forward);
        $backward = self::applyBiquad($reversed, $coeff);
        return array_reverse($backward);
    }

    /**
     * Common Average Reference (CAR) spatial filter across multi-channel EEG montage
     * Removes common-mode volume-conduction noise
     *
     * @param array<string, float[]> $channelSignals Keyed by channel name
     * @return array<string, float[]> CAR-filtered channels
     */
    public static function commonAverageReference(array $channelSignals): array
    {
        if (empty($channelSignals)) {
            return [];
        }

        $channels = array_keys($channelSignals);
        $numChannels = count($channels);
        $signalLength = count(reset($channelSignals));

        $filtered = [];
        foreach ($channels as $ch) {
            $filtered[$ch] = array_fill(0, $signalLength, 0.0);
        }

        for ($t = 0; $t < $signalLength; $t++) {
            $sum = 0.0;
            foreach ($channels as $ch) {
                $sum += $channelSignals[$ch][$t] ?? 0.0;
            }
            $mean = $sum / $numChannels;

            foreach ($channels as $ch) {
                $filtered[$ch][$t] = ($channelSignals[$ch][$t] ?? 0.0) - $mean;
            }
        }

        return $filtered;
    }

    /**
     * Replaces or clips physiological and motion artifacts exceeding microvolt threshold
     *
     * @param float[] $signal
     * @param float $thresholdMicrovolts Absolute threshold (default 100.0 uV)
     * @return array{cleaned: float[], artifactCount: int}
     */
    public static function rejectArtifacts(array $signal, float $thresholdMicrovolts = 100.0): array
    {
        $cleaned = [];
        $artifactCount = 0;

        foreach ($signal as $val) {
            if (abs($val) > $thresholdMicrovolts) {
                $cleaned[] = ($val > 0) ? $thresholdMicrovolts : -$thresholdMicrovolts;
                $artifactCount++;
            } else {
                $cleaned[] = $val;
            }
        }

        return [
            'cleaned' => $cleaned,
            'artifactCount' => $artifactCount,
        ];
    }
}
