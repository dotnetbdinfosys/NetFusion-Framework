<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * Nucleotide sequence representation, Central Dogma transcription, and translation engine.
 *
 * @package    NetFusion\Core\Bio\Sequence
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio\Sequence;

use NetFusion\Core\Bio\Exceptions\InvalidSequenceException;

class DnaSequence
{
    /**
     * Standard Genetic Code mapping 64 triplets to IUPAC amino acid single-letter codes (* = STOP).
     *
     * @var array<string, string>
     */
    public const GENETIC_CODE = [
        'TTT' => 'F', 'TTC' => 'F', 'TTA' => 'L', 'TTG' => 'L',
        'TCT' => 'S', 'TCC' => 'S', 'TCA' => 'S', 'TCG' => 'S',
        'TAT' => 'Y', 'TAC' => 'Y', 'TAA' => '*', 'TAG' => '*',
        'TGT' => 'C', 'TGC' => 'C', 'TGA' => '*', 'TGG' => 'W',

        'CTT' => 'L', 'CTC' => 'L', 'CTA' => 'L', 'CTG' => 'L',
        'CCT' => 'P', 'CCC' => 'P', 'CCA' => 'P', 'CCG' => 'P',
        'CAT' => 'H', 'CAC' => 'H', 'CAA' => 'Q', 'CAG' => 'Q',
        'CGT' => 'R', 'CGC' => 'R', 'CGA' => 'R', 'CGG' => 'R',

        'ATT' => 'I', 'ATC' => 'I', 'ATA' => 'I', 'ATG' => 'M',
        'ACT' => 'T', 'ACC' => 'T', 'ACA' => 'T', 'ACG' => 'T',
        'AAT' => 'N', 'AAC' => 'N', 'AAA' => 'K', 'AAG' => 'K',
        'AGT' => 'S', 'AGC' => 'S', 'AGA' => 'R', 'AGG' => 'R',

        'GTT' => 'V', 'GTC' => 'V', 'GTA' => 'V', 'GTG' => 'V',
        'GCT' => 'A', 'GCC' => 'A', 'GCA' => 'A', 'GCG' => 'A',
        'GAT' => 'D', 'GAC' => 'D', 'GAA' => 'E', 'GAG' => 'E',
        'GGT' => 'G', 'GGC' => 'G', 'GGA' => 'G', 'GGG' => 'G',
    ];

    protected string $sequence;

    public function __construct(string $rawSequence)
    {
        $sanitized = strtoupper(trim(preg_replace('/\s+/', '', $rawSequence) ?? ''));
        if ($sanitized !== '' && preg_match('/[^ACGT]/', $sanitized)) {
            throw new InvalidSequenceException("DNA sequence contains non-canonical characters: {$rawSequence}");
        }
        $this->sequence = $sanitized;
    }

    public function length(): int
    {
        return strlen($this->sequence);
    }

    public function getSequence(): string
    {
        return $this->sequence;
    }

    /**
     * Compute GC content percentage (0.0 to 100.0%).
     */
    public function gcContent(): float
    {
        $len = strlen($this->sequence);
        if ($len === 0) {
            return 0.0;
        }

        $g = substr_count($this->sequence, 'G');
        $c = substr_count($this->sequence, 'C');

        return (($g + $c) / $len) * 100.0;
    }

    /**
     * Generate reverse complement strand (5' to 3').
     */
    public function reverseComplement(): self
    {
        $complement = strtr($this->sequence, 'ACGT', 'TGCA');
        return new self(strrev($complement));
    }

    /**
     * Transcribe DNA coding strand into messenger RNA (T -> U).
     */
    public function transcribe(): string
    {
        return str_replace('T', 'U', $this->sequence);
    }

    /**
     * Translate Open Reading Frame into amino acid peptide string.
     */
    public function translate(int $readingFrame = 0): string
    {
        $len = strlen($this->sequence);
        $peptide = '';

        for ($i = $readingFrame; $i + 2 < $len; $i += 3) {
            $triplet = substr($this->sequence, $i, 3);
            $peptide .= self::GENETIC_CODE[$triplet] ?? '?';
        }

        return $peptide;
    }

    /**
     * Extract a sub-sequence slice.
     */
    public function subsequence(int $start, int $length): self
    {
        return new self(substr($this->sequence, $start, $length));
    }

    /**
     * Compute Hamming mismatch distance against another sequence of equal length.
     */
    public function hammingDistance(self|string $other): int
    {
        $otherSeq = $other instanceof self ? $other->getSequence() : strtoupper(trim($other));
        $len = min(strlen($this->sequence), strlen($otherSeq));
        $diff = abs(strlen($this->sequence) - strlen($otherSeq));

        for ($i = 0; $i < $len; $i++) {
            if ($this->sequence[$i] !== $otherSeq[$i]) {
                $diff++;
            }
        }

        return $diff;
    }

    /**
     * Find all regex motif occurrences with 0-based positions.
     *
     * @return array<int, array{position: int, match: string}>
     */
    public function findMotifs(string $pattern): array
    {
        $matches = [];
        preg_match_all('/' . $pattern . '/', $this->sequence, $raw, PREG_OFFSET_CAPTURE);

        if (!empty($raw[0])) {
            foreach ($raw[0] as $match) {
                $matches[] = [
                    'position' => (int) $match[1],
                    'match'    => (string) $match[0],
                ];
            }
        }

        return $matches;
    }

    public function __toString(): string
    {
        return $this->sequence;
    }
}
