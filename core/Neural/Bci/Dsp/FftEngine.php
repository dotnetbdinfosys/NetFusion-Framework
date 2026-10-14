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

use NetFusion\Core\Neural\Bci\Enums\BrainWaveBand;

class FftEngine
{
    /**
     * Compute next power of two
     */
    public static function nextPowerOfTwo(int $n): int
    {
        $p = 1;
        while ($p < $n) {
            $p <<= 1;
        }
        return $p;
    }

    /**
     * Generate Hanning window of length N
     *
     * @return float[]
     */
    public static function hanningWindow(int $n): array
    {
        if ($n <= 1) {
            return [1.0];
        }

        $w = [];
        for ($i = 0; $i < $n; $i++) {
            $w[] = 0.5 * (1.0 - cos(2.0 * M_PI * $i / ($n - 1)));
        }
        return $w;
    }

    /**
     * Radix-2 In-place Cooley-Tukey Fast Fourier Transform (FFT)
     *
     * @param float[] $real Input real components
     * @param float[]|null $imag Input imaginary components (defaults to zeros)
     * @return array{real: float[], imag: float[]}
     */
    public static function fft(array $real, ?array $imag = null): array
    {
        $n = count($real);
        $paddedN = self::nextPowerOfTwo($n);

        // Pad to next power of 2
        $r = array_pad($real, $paddedN, 0.0);
        $im = ($imag === null) ? array_fill(0, $paddedN, 0.0) : array_pad($imag, $paddedN, 0.0);

        // Bit-reversal permutation
        $j = 0;
        for ($i = 0; $i < $paddedN - 1; $i++) {
            if ($i < $j) {
                // Swap r[i], r[j]
                $tempR = $r[$i];
                $r[$i] = $r[$j];
                $r[$j] = $tempR;

                $tempIm = $im[$i];
                $im[$i] = $im[$j];
                $im[$j] = $tempIm;
            }
            $k = $paddedN >> 1;
            while ($k <= $j) {
                $j -= $k;
                $k >>= 1;
            }
            $j += $k;
        }

        // Cooley-Tukey butterflies
        for ($len = 2; $len <= $paddedN; $len <<= 1) {
            $angle = -2.0 * M_PI / $len;
            $wStepR = cos($angle);
            $wStepIm = sin($angle);

            for ($i = 0; $i < $paddedN; $i += $len) {
                $wR = 1.0;
                $wIm = 0.0;
                $half = $len >> 1;

                for ($k = 0; $k < $half; $k++) {
                    $uR = $r[$i + $k];
                    $uIm = $im[$i + $k];

                    $vR = $r[$i + $k + $half] * $wR - $im[$i + $k + $half] * $wIm;
                    $vIm = $r[$i + $k + $half] * $wIm + $im[$i + $k + $half] * $wR;

                    $r[$i + $k] = $uR + $vR;
                    $im[$i + $k] = $uIm + $vIm;
                    $r[$i + $k + $half] = $uR - $vR;
                    $im[$i + $k + $half] = $uIm - $vIm;

                    $nextWR = $wR * $wStepR - $wIm * $wStepIm;
                    $wIm = $wR * $wStepIm + $wIm * $wStepR;
                    $wR = $nextWR;
                }
            }
        }

        return ['real' => $r, 'imag' => $im];
    }

    /**
     * Compute Power Spectral Density (PSD) using Welch's method with 50% overlap
     *
     * @param float[] $signal Time-domain signal
     * @param float $samplingRate Sampling frequency in Hz
     * @param int $segmentLength Length of windowed segments (e.g. 128 or 256)
     * @return array{freqs: float[], psd: float[]}
     */
    public static function welchPsd(array $signal, float $samplingRate, int $segmentLength = 128): array
    {
        $sigLen = count($signal);
        if ($sigLen < $segmentLength) {
            $segmentLength = self::nextPowerOfTwo($sigLen);
        }

        $window = self::hanningWindow($segmentLength);
        $winNorm = 0.0;
        foreach ($window as $w) {
            $winNorm += $w * $w;
        }
        $winNorm = max(1e-12, $winNorm * $samplingRate);

        $step = max(1, (int)($segmentLength / 2)); // 50% overlap
        $numBins = ($segmentLength >> 1) + 1;
        $accumPsd = array_fill(0, $numBins, 0.0);
        $segmentsCount = 0;

        for ($start = 0; $start + $segmentLength <= $sigLen; $start += $step) {
            $seg = [];
            for ($i = 0; $i < $segmentLength; $i++) {
                $seg[] = $signal[$start + $i] * $window[$i];
            }

            $fftRes = self::fft($seg);
            $real = $fftRes['real'];
            $imag = $fftRes['imag'];

            for ($k = 0; $k < $numBins; $k++) {
                $power = ($real[$k] * $real[$k] + $imag[$k] * $imag[$k]) / $winNorm;
                if ($k > 0 && $k < $numBins - 1) {
                    $power *= 2.0; // Conserve single-sided spectral power
                }
                $accumPsd[$k] += $power;
            }
            $segmentsCount++;
        }

        if ($segmentsCount === 0) {
            // Handle signal shorter than window by padding
            $seg = array_pad($signal, $segmentLength, 0.0);
            for ($i = 0; $i < $segmentLength; $i++) {
                $seg[$i] *= $window[$i];
            }
            $fftRes = self::fft($seg);
            for ($k = 0; $k < $numBins; $k++) {
                $power = ($fftRes['real'][$k] ** 2 + $fftRes['imag'][$k] ** 2) / $winNorm;
                if ($k > 0 && $k < $numBins - 1) {
                    $power *= 2.0;
                }
                $accumPsd[$k] = $power;
            }
            $segmentsCount = 1;
        }

        $freqs = [];
        $finalPsd = [];
        $df = $samplingRate / $segmentLength;

        for ($k = 0; $k < $numBins; $k++) {
            $freqs[] = $k * $df;
            $finalPsd[] = $accumPsd[$k] / $segmentsCount;
        }

        return ['freqs' => $freqs, 'psd' => $finalPsd];
    }

    /**
     * Compute integrated band power within specified frequency range
     *
     * @param float[] $freqs
     * @param float[] $psd
     */
    public static function computeBandPower(array $freqs, array $psd, float $lowFreq, float $highFreq): float
    {
        $power = 0.0;
        $count = count($freqs);

        for ($i = 0; $i < $count - 1; $i++) {
            $f1 = $freqs[$i];
            $f2 = $freqs[$i + 1];

            if ($f2 >= $lowFreq && $f1 <= $highFreq) {
                $df = $f2 - $f1;
                $avgPsd = ($psd[$i] + $psd[$i + 1]) / 2.0;
                $power += $avgPsd * $df;
            }
        }

        return max(1e-12, $power);
    }

    /**
     * Extract band powers and relative ratios for all BrainWave bands
     *
     * @param float[] $signal
     * @param float $samplingRate
     * @return array{absolute: array<string, float>, relative: array<string, float>, totalPower: float}
     */
    public static function extractBandPowers(array $signal, float $samplingRate): array
    {
        $welch = self::welchPsd($signal, $samplingRate);
        $freqs = $welch['freqs'];
        $psd = $welch['psd'];

        $absolute = [];
        $totalPower = 0.0;

        foreach (BrainWaveBand::cases() as $band) {
            $bp = self::computeBandPower($freqs, $psd, $band->minFrequency(), $band->maxFrequency());
            $absolute[$band->value] = $bp;
            $totalPower += $bp;
        }

        $relative = [];
        foreach ($absolute as $bandKey => $val) {
            $relative[$bandKey] = $totalPower > 0 ? ($val / $totalPower) : 0.0;
        }

        return [
            'absolute' => $absolute,
            'relative' => $relative,
            'totalPower' => $totalPower,
        ];
    }
}
