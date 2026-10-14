<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * CRISPR gRNA design engine and MIT/CFD off-target cleavage risk prediction.
 *
 * @package    NetFusion\Core\Bio\Crispr
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Crispr;

use NetFusion\Core\Bio\Enums\CasVariant;
use NetFusion\Core\Bio\Sequence\DnaSequence;

class CrisprEngine
{
    /**
     * Hsu/Zhang et al. (MIT 2013) mismatch positional penalty matrix for SpCas9 (positions 1..20).
     *
     * Higher weight = position is strictly sensitive to mismatch (seed region 10-20).
     *
     * @var array<int, float>
     */
    public const MIT_WEIGHTS = [
        1  => 0.0,   2  => 0.0,   3  => 0.014, 4  => 0.0,   5  => 0.0,
        6  => 0.395, 7  => 0.317, 8  => 0.0,   9  => 0.389, 10 => 0.079,
        11 => 0.445, 12 => 0.508, 13 => 0.613, 14 => 0.851, 15 => 0.732,
        16 => 0.828, 17 => 0.615, 18 => 0.804, 19 => 0.685, 20 => 0.583,
    ];

    /**
     * Design high-specificity candidate guide RNAs targeting a DNA region.
     *
     * @return array<int, array{spacer: string, pam: string, position: int, strand: string, gc: float, on_target_score: float}>
     */
    public function designGuideRnas(DnaSequence $target, CasVariant $variant = CasVariant::SpCas9): array
    {
        $seq = $target->getSequence();
        $len = strlen($seq);
        $spacerLen = $variant->spacerLength();
        $guides = [];

        // Scan Forward Strand
        $this->scanStrand($seq, $spacerLen, $variant, '+', $guides);

        // Scan Reverse Strand
        $revComp = $target->reverseComplement()->getSequence();
        $this->scanStrand($revComp, $spacerLen, $variant, '-', $guides);

        // Sort descending by on-target score
        usort($guides, fn($a, $b) => $b['on_target_score'] <=> $a['on_target_score']);

        return $guides;
    }

    /**
     * Helper to scan a single DNA strand for PAM motifs and extract spacers.
     *
     * @param array<int, array> $guides
     */
    protected function scanStrand(string $seq, int $spacerLen, CasVariant $variant, string $strand, array &$guides): void
    {
        $len = strlen($seq);
        $pamPattern = '/' . $variant->pamPattern() . '/';

        preg_match_all($pamPattern, $seq, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return;
        }

        foreach ($matches[0] as $match) {
            $pamStr = (string) $match[0];
            $pamPos = (int) $match[1];

            if ($variant->isPamAtThreePrime()) {
                // PAM at 3' end: spacer is preceding N-mer
                $spacerStart = $pamPos - $spacerLen;
                if ($spacerStart >= 0) {
                    $spacer = substr($seq, $spacerStart, $spacerLen);
                    $guides[] = $this->evaluateGuide($spacer, $pamStr, $spacerStart, $strand);
                }
            } else {
                // PAM at 5' end: spacer is following N-mer
                $spacerStart = $pamPos + strlen($pamStr);
                if ($spacerStart + $spacerLen <= $len) {
                    $spacer = substr($seq, $spacerStart, $spacerLen);
                    $guides[] = $this->evaluateGuide($spacer, $pamStr, $pamPos, $strand);
                }
            }
        }
    }

    /**
     * Evaluate on-target efficiency score and filter sequence flags.
     *
     * @return array{spacer: string, pam: string, position: int, strand: string, gc: float, on_target_score: float}
     */
    protected function evaluateGuide(string $spacer, string $pam, int $position, string $strand): array
    {
        $spacerSeq = new DnaSequence($spacer);
        $gc = $spacerSeq->gcContent();

        // Baseline score 100
        $score = 100.0;

        // Ideal GC range is 40% - 65%
        if ($gc < 40.0) {
            $score -= (40.0 - $gc) * 1.5;
        } elseif ($gc > 65.0) {
            $score -= ($gc - 65.0) * 1.5;
        }

        // Penalize Poly-T motifs (TTTT aborts U6 Pol III transcription)
        if (str_contains($spacer, 'TTTT')) {
            $score -= 35.0;
        }

        // Position 20 preference: 'G' strongly enhances Cas9 loading
        if (str_ends_with($spacer, 'G')) {
            $score += 5.0;
        }

        return [
            'spacer'          => $spacer,
            'pam'             => $pam,
            'position'        => $position,
            'strand'          => $strand,
            'gc'              => round($gc, 1),
            'on_target_score' => max(0.0, min(100.0, round($score, 1))),
        ];
    }

    /**
     * Compute MIT Off-Target Cleavage Probability Score between spacer and genomic off-target candidate.
     *
     * Returns cutting probability score from 0.0 (completely safe / zero cleavage) to 100.0 (exact match / 100% cleavage).
     */
    public function calculateOffTargetScore(string $spacer, string $offTargetProtospacer): float
    {
        $s1 = strtoupper(trim($spacer));
        $s2 = strtoupper(trim($offTargetProtospacer));
        $len = min(strlen($s1), strlen($s2));

        if ($s1 === $s2) {
            return 100.0; // Perfect match
        }

        $mismatches = [];
        for ($i = 0; $i < $len; $i++) {
            if ($s1[$i] !== $s2[$i]) {
                $mismatches[] = $i + 1; // 1-based index (1..20)
            }
        }

        $mCount = count($mismatches);
        if ($mCount === 0) {
            return 100.0;
        }

        // Too many mismatches (> 4) prevents Cas9 R-loop activation
        if ($mCount > 4) {
            return 0.0;
        }

        // Positional factor: product of (1 - W[k])
        $posFactor = 1.0;
        foreach ($mismatches as $pos) {
            $w = self::MIT_WEIGHTS[$pos] ?? 0.5;
            $posFactor *= (1.0 - $w);
        }

        // Mean pairwise distance between mismatches
        $meanDist = 19.0;
        if ($mCount > 1) {
            $totalDist = 0;
            $pairs = 0;
            for ($i = 0; $i < $mCount - 1; $i++) {
                $totalDist += abs($mismatches[$i + 1] - $mismatches[$i]);
                $pairs++;
            }
            $meanDist = $pairs > 0 ? ($totalDist / $pairs) : 19.0;
        }

        $distFactor = 1.0 / ((((19.0 - $meanDist) / 19.0) * 4.0) + 1.0);
        $countFactor = 1.0 / ($mCount * $mCount);

        $mitScore = $posFactor * $distFactor * $countFactor * 100.0;

        return max(0.0, min(100.0, round($mitScore, 2)));
    }
}
