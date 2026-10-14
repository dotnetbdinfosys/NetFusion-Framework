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

namespace NetFusion\Core\Search\Scoring;

class BM25Scorer
{
    /**
     * @param float $k1 Term frequency saturation parameter (standard 1.2 to 2.0)
     * @param float $b Document length normalization parameter (standard 0.75)
     */
    public function __construct(
        protected float $k1 = 1.2,
        protected float $b = 0.75
    ) {
    }

    /**
     * Calculate Okapi BM25 relevance score for a single term in a document.
     *
     * Formula:
     * IDF = ln(1 + (N - n + 0.5) / (n + 0.5))
     * BM25 = IDF * (TF * (k1 + 1)) / (TF + k1 * (1 - b + b * (docLength / avgDocLength)))
     *
     * @param int $tf Term frequency in document
     * @param int $df Document frequency of term across collection
     * @param int $n Total documents in collection
     * @param int $docLength Length of current document
     * @param float $avgDocLength Average document length across collection
     * @return float
     */
    public function score(int $tf, int $df, int $n, int $docLength, float $avgDocLength): float
    {
        if ($tf <= 0 || $n <= 0) {
            return 0.0;
        }

        // Robertson-Spärck Jones Inverse Document Frequency (with smoothing to prevent negative IDF)
        $numerator = max(0.0, ($n - $df + 0.5));
        $denominator = ($df + 0.5);
        $idf = log(1.0 + ($numerator / $denominator));

        // Term Frequency component with document length normalization
        $avgDL = max(1.0, $avgDocLength);
        $lenNorm = (1.0 - $this->b) + ($this->b * ($docLength / $avgDL));
        $tfComp = ($tf * ($this->k1 + 1.0)) / ($tf + ($this->k1 * $lenNorm));

        return round($idf * $tfComp, 6);
    }

    /**
     * Compute Lucene/BM25 style Inverse Document Frequency (IDF).
     */
    public function idf(int $df, int $n): float
    {
        $numerator = max(0.0, ($n - $df + 0.5));
        $denominator = ($df + 0.5);
        return log(1.0 + ($numerator / $denominator));
    }
}
