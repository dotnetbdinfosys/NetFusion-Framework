<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Holographic Storage & DNA Molecular Data Encoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Storage\Molecular\Dna;

use NetFusion\Core\Storage\Molecular\Enums\Nucleotide;
use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class NucleotideSequence
{
    protected string $sequence;

    public function __construct(string $sequence)
    {
        $clean = strtoupper(trim($sequence));
        if ($clean !== '' && !preg_match('/^[ACGT]+$/', $clean)) {
            throw new MolecularStorageException("Invalid nucleotide characters detected. Only A, C, G, T are valid bases.");
        }
        $this->sequence = $clean;
    }

    public function getSequence(): string
    {
        return $this->sequence;
    }

    public function length(): int
    {
        return strlen($this->sequence);
    }

    /**
     * Compute GC content ratio: (G + C) / total_length
     */
    public function getGcContent(): float
    {
        $len = $this->length();
        if ($len === 0) {
            return 0.0;
        }

        $gc = substr_count($this->sequence, 'G') + substr_count($this->sequence, 'C');
        return round($gc / $len, 4);
    }

    /**
     * Find the longest consecutive run of identical nucleotides (homopolymer run)
     */
    public function getMaxHomopolymerRun(): int
    {
        $len = $this->length();
        if ($len === 0) {
            return 0;
        }

        $maxRun = 1;
        $currentRun = 1;

        for ($i = 1; $i < $len; $i++) {
            if ($this->sequence[$i] === $this->sequence[$i - 1]) {
                $currentRun++;
                if ($currentRun > $maxRun) {
                    $maxRun = $currentRun;
                }
            } else {
                $currentRun = 1;
            }
        }

        return $maxRun;
    }

    /**
     * Compute Watson-Crick reverse complement
     */
    public function reverseComplement(): self
    {
        $rev = strrev($this->sequence);
        $len = strlen($rev);
        $comp = '';

        for ($i = 0; $i < $len; $i++) {
            $base = Nucleotide::tryFrom($rev[$i]);
            $comp .= $base ? $base->complement()->value : $rev[$i];
        }

        return new self($comp);
    }

    public function __toString(): string
    {
        return $this->sequence;
    }
}
