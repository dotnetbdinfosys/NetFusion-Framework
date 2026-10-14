<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Experimentation\Statistics;

class StatisticalEngine
{
    /**
     * Compute comprehensive A/B experiment statistical metrics.
     *
     * @param array<string, int>   $exposuresPerVariant   ['control' => 1000, 'treatment' => 1020]
     * @param array<string, int>   $conversionsPerVariant ['control' => 120, 'treatment' => 165]
     * @param array<string, float> $revenuePerVariant     ['control' => 1200.0, 'treatment' => 1850.0]
     * @param string               $controlVariantName    Default 'control'
     * @param float                $alpha                 Significance level (default 0.05 for 95% confidence)
     * @return array<string, mixed>
     */
    public static function analyze(
        array $exposuresPerVariant,
        array $conversionsPerVariant,
        array $revenuePerVariant = [],
        string $controlVariantName = 'control',
        float $alpha = 0.05
    ): array {
        $results = [
            'total_exposures' => array_sum($exposuresPerVariant),
            'total_conversions' => array_sum($conversionsPerVariant),
            'variants' => [],
            'comparisons' => [],
            'winner' => null,
        ];

        // 1. Per-Variant Baseline Metrics
        foreach ($exposuresPerVariant as $variant => $exposures) {
            $conversions = $conversionsPerVariant[$variant] ?? 0;
            $revenue = $revenuePerVariant[$variant] ?? 0.0;
            $conversionRate = $exposures > 0 ? round(($conversions / $exposures) * 100, 4) : 0.0;
            $arpu = $exposures > 0 ? round($revenue / $exposures, 4) : 0.0;

            $results['variants'][$variant] = [
                'name' => $variant,
                'exposures' => $exposures,
                'conversions' => $conversions,
                'conversion_rate_pct' => $conversionRate,
                'revenue' => $revenue,
                'arpu' => $arpu,
            ];
        }

        // If control variant is not in list, use first variant
        if (!isset($results['variants'][$controlVariantName])) {
            $controlVariantName = (string)array_key_first($exposuresPerVariant);
        }

        $ctrlExposures = $exposuresPerVariant[$controlVariantName] ?? 0;
        $ctrlConversions = $conversionsPerVariant[$controlVariantName] ?? 0;
        $ctrlRate = $ctrlExposures > 0 ? ($ctrlConversions / $ctrlExposures) : 0.0;

        $bestUplift = 0.0;
        $winningVariant = null;

        // 2. Statistical Two-Sample Z-Test for Proportions vs Control
        foreach ($exposuresPerVariant as $variant => $treatExposures) {
            if ($variant === $controlVariantName) {
                continue;
            }

            $treatConversions = $conversionsPerVariant[$variant] ?? 0;
            $treatRate = $treatExposures > 0 ? ($treatConversions / $treatExposures) : 0.0;

            $diff = $treatRate - $ctrlRate;
            $upliftPct = $ctrlRate > 0 ? round(($diff / $ctrlRate) * 100, 2) : 0.0;

            $comparison = [
                'variant' => $variant,
                'control' => $controlVariantName,
                'absolute_diff' => round($diff * 100, 4),
                'relative_uplift_pct' => $upliftPct,
                'z_score' => 0.0,
                'p_value' => 1.0,
                'confidence_pct' => 0.0,
                'is_significant' => false,
                'sample_size_adequate' => ($ctrlExposures >= 30 && $treatExposures >= 30),
            ];

            if ($ctrlExposures > 0 && $treatExposures > 0) {
                $totalN = $ctrlExposures + $treatExposures;
                $totalC = $ctrlConversions + $treatConversions;
                $pooledP = $totalC / $totalN;

                if ($pooledP > 0 && $pooledP < 1) {
                    $se = sqrt($pooledP * (1.0 - $pooledP) * ((1.0 / $ctrlExposures) + (1.0 / $treatExposures)));
                    if ($se > 0) {
                        $zScore = $diff / $se;
                        $pValue = self::calculatePValue($zScore);
                        $confidence = round((1.0 - $pValue) * 100, 2);
                        $isSignificant = ($pValue <= $alpha);

                        $comparison['z_score'] = round($zScore, 4);
                        $comparison['p_value'] = round($pValue, 6);
                        $comparison['confidence_pct'] = max(0.0, min(100.0, $confidence));
                        $comparison['is_significant'] = $isSignificant;

                        if ($isSignificant && $upliftPct > $bestUplift) {
                            $bestUplift = $upliftPct;
                            $winningVariant = $variant;
                        }
                    }
                }
            }

            $results['comparisons'][$variant] = $comparison;
        }

        $results['winner'] = $winningVariant;
        return $results;
    }

    /**
     * Compute two-tailed P-Value from Z-score using Error Function (erf).
     */
    public static function calculatePValue(float $z): float
    {
        $absZ = abs($z);
        $cdf = 0.5 * (1.0 + self::erf($absZ / sqrt(2.0)));
        $pValue = 2.0 * (1.0 - $cdf);
        return max(0.0, min(1.0, $pValue));
    }

    /**
     * Approximation of Gaussian error function erf(x) via Abramowitz and Stegun formula 7.1.26.
     */
    public static function erf(float $x): float
    {
        $sign = ($x < 0) ? -1.0 : 1.0;
        $x = abs($x);

        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p  = 0.3275911;

        $t = 1.0 / (1.0 + $p * $x);
        $poly = $t * ($a1 + $t * ($a2 + $t * ($a3 + $t * ($a4 + $t * $a5))));
        $y = 1.0 - ($poly * exp(-$x * $x));

        return $sign * $y;
    }
}
