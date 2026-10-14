<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * Species-specific codon usage bias optimization and Codon Adaptation Index (CAI) engine.
 *
 * @package    NetFusion\Core\Bio\Codon
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Codon;

use NetFusion\Core\Bio\Enums\OrganismHost;
use NetFusion\Core\Bio\Sequence\DnaSequence;

class CodonOptimizer
{
    /**
     * Most preferred synonymous codons per amino acid for each host organism.
     *
     * @var array<string, array<string, string>>
     */
    public const PREFERRED_CODONS = [
        OrganismHost::E_COLI->value => [
            'A' => 'GCG', 'R' => 'CGT', 'N' => 'AAC', 'D' => 'GAT', 'C' => 'TGC',
            'Q' => 'CAG', 'E' => 'GAA', 'G' => 'GGC', 'H' => 'CAC', 'I' => 'ATC',
            'L' => 'CTG', 'K' => 'AAA', 'M' => 'ATG', 'F' => 'TTC', 'P' => 'CCG',
            'S' => 'AGC', 'T' => 'ACC', 'W' => 'TGG', 'Y' => 'TAC', 'V' => 'GTG',
            '*' => 'TAA',
        ],
        OrganismHost::H_SAPIENS->value => [
            'A' => 'GCC', 'R' => 'CGG', 'N' => 'AAC', 'D' => 'GAC', 'C' => 'TGC',
            'Q' => 'CAG', 'E' => 'GAG', 'G' => 'GGC', 'H' => 'CAC', 'I' => 'ATC',
            'L' => 'CTG', 'K' => 'AAG', 'M' => 'ATG', 'F' => 'TTC', 'P' => 'CCC',
            'S' => 'AGC', 'T' => 'ACC', 'W' => 'TGG', 'Y' => 'TAC', 'V' => 'GTG',
            '*' => 'TGA',
        ],
        OrganismHost::S_CEREVISIAE->value => [
            'A' => 'GCT', 'R' => 'AGA', 'N' => 'AAC', 'D' => 'GAT', 'C' => 'TGT',
            'Q' => 'CAA', 'E' => 'GAA', 'G' => 'GGT', 'H' => 'CAC', 'I' => 'ATT',
            'L' => 'TTG', 'K' => 'AAG', 'M' => 'ATG', 'F' => 'TTC', 'P' => 'CCA',
            'S' => 'TCT', 'T' => 'ACT', 'W' => 'TGG', 'Y' => 'TAC', 'V' => 'GTT',
            '*' => 'TAA',
        ],
        OrganismHost::CHO_CELLS->value => [
            'A' => 'GCC', 'R' => 'CGG', 'N' => 'AAC', 'D' => 'GAC', 'C' => 'TGC',
            'Q' => 'CAG', 'E' => 'GAG', 'G' => 'GGC', 'H' => 'CAC', 'I' => 'ATC',
            'L' => 'CTG', 'K' => 'AAG', 'M' => 'ATG', 'F' => 'TTC', 'P' => 'CCC',
            'S' => 'AGC', 'T' => 'ACC', 'W' => 'TGG', 'Y' => 'TAC', 'V' => 'GTG',
            '*' => 'TGA',
        ],
    ];

    /**
     * Relative adaptiveness weights (w_ij) for E. coli codons (sample representative table).
     *
     * @var array<string, float>
     */
    public const E_COLI_WEIGHTS = [
        'TTT' => 0.30, 'TTC' => 1.00, 'TTA' => 0.14, 'TTG' => 0.13,
        'TCT' => 0.84, 'TCC' => 0.74, 'TCA' => 0.14, 'TCG' => 0.03,
        'TAT' => 0.43, 'TAC' => 1.00, 'TAA' => 1.00, 'TAG' => 0.01,
        'TGT' => 0.49, 'TGC' => 1.00, 'TGA' => 0.09, 'TGG' => 1.00,

        'CTT' => 0.04, 'CTC' => 0.04, 'CTA' => 0.01, 'CTG' => 1.00,
        'CCT' => 0.07, 'CCC' => 0.01, 'CCA' => 0.14, 'CCG' => 1.00,
        'CAT' => 0.29, 'CAC' => 1.00, 'CAA' => 0.12, 'CAG' => 1.00,
        'CGT' => 1.00, 'CGC' => 0.98, 'CGA' => 0.01, 'CGG' => 0.01,

        'ATT' => 0.51, 'ATC' => 1.00, 'ATA' => 0.01, 'ATG' => 1.00,
        'ACT' => 0.90, 'ACC' => 1.00, 'ACA' => 0.07, 'ACG' => 0.14,
        'AAT' => 0.10, 'AAC' => 1.00, 'AAA' => 1.00, 'AAG' => 0.25,
        'AGT' => 0.16, 'AGC' => 1.00, 'AGA' => 0.01, 'AGG' => 0.01,

        'GTT' => 0.79, 'GTC' => 0.06, 'GTA' => 0.50, 'GTG' => 1.00,
        'GCT' => 0.78, 'GCC' => 0.28, 'GCA' => 0.59, 'GCG' => 1.00,
        'GAT' => 0.43, 'GAC' => 1.00, 'GAA' => 1.00, 'GAG' => 0.26,
        'GGT' => 0.59, 'GGC' => 1.00, 'GGA' => 0.01, 'GGG' => 0.01,
    ];

    /**
     * Compute Codon Adaptation Index (CAI) of a coding sequence.
     *
     * CAI = exp( (1/L) * sum(ln(w_k)) )
     */
    public function calculateCai(DnaSequence $sequence, OrganismHost $host = OrganismHost::E_COLI): float
    {
        $seq = $sequence->getSequence();
        $len = strlen($seq);
        $logSum = 0.0;
        $codonCount = 0;

        for ($i = 0; $i + 2 < $len; $i += 3) {
            $triplet = substr($seq, $i, 3);
            $weight = self::E_COLI_WEIGHTS[$triplet] ?? 0.50;
            // Prevent log(0)
            $w = max(0.01, $weight);
            $logSum += log($w);
            $codonCount++;
        }

        if ($codonCount === 0) {
            return 1.0;
        }

        $cai = exp($logSum / $codonCount);
        return max(0.0, min(1.0, round($cai, 4)));
    }

    /**
     * Optimize coding sequence for target host organism using synonymous substitution.
     *
     * @return array{optimized: DnaSequence, original_cai: float, optimized_cai: float, substitutions: int}
     */
    public function optimize(DnaSequence $sequence, OrganismHost $host = OrganismHost::E_COLI): array
    {
        $seq = $sequence->getSequence();
        $len = strlen($seq);
        $preferred = self::PREFERRED_CODONS[$host->value] ?? self::PREFERRED_CODONS[OrganismHost::E_COLI->value];

        $optimizedSeq = '';
        $subsCount = 0;

        for ($i = 0; $i + 2 < $len; $i += 3) {
            $codon = substr($seq, $i, 3);
            $aa = DnaSequence::GENETIC_CODE[$codon] ?? null;

            if ($aa !== null && isset($preferred[$aa])) {
                $targetCodon = $preferred[$aa];
                if ($targetCodon !== $codon) {
                    $subsCount++;
                }
                $optimizedSeq .= $targetCodon;
            } else {
                $optimizedSeq .= $codon;
            }
        }

        $optimizedDna = new DnaSequence($optimizedSeq);

        return [
            'optimized'     => $optimizedDna,
            'original_cai'  => $this->calculateCai($sequence, $host),
            'optimized_cai' => $this->calculateCai($optimizedDna, $host),
            'substitutions' => $subsCount,
        ];
    }
}
