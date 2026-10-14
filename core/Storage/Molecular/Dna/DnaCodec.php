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

use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class DnaCodec
{
    // Goldman ternary rotational transition tables (Guarantees MAX homopolymer run = 1)
    protected const ROTATION_TABLE = [
        'A' => ['C', 'G', 'T'],
        'C' => ['G', 'T', 'A'],
        'G' => ['T', 'A', 'C'],
        'T' => ['A', 'C', 'G'],
    ];

    protected string $forwardPrimer;
    protected string $reversePrimer;

    public function __construct(
        string $forwardPrimer = 'ACGTACGT',
        string $reversePrimer = 'TGCATGCA'
    ) {
        $this->forwardPrimer = strtoupper($forwardPrimer);
        $this->reversePrimer = strtoupper($reversePrimer);
    }

    /**
     * Encode binary string into homopolymer-free NucleotideSequence
     *
     * @param string $binaryData
     * @param bool $includePrimers
     * @return NucleotideSequence
     */
    public function encode(string $binaryData, bool $includePrimers = true): NucleotideSequence
    {
        $bytes = array_values(unpack('C*', $binaryData));
        $sequence = '';
        $prevBase = 'A'; // Seed nucleotide

        foreach ($bytes as $byte) {
            // Convert byte (0-255) to 6 trits (base 3: 3^6 = 729 > 256)
            $trits = [];
            $val = $byte;
            for ($i = 0; $i < 6; $i++) {
                $trits[] = $val % 3;
                $val = intdiv($val, 3);
            }

            foreach ($trits as $trit) {
                $nextBase = self::ROTATION_TABLE[$prevBase][$trit];
                $sequence .= $nextBase;
                $prevBase = $nextBase;
            }
        }

        if ($includePrimers) {
            $sequence = $this->forwardPrimer . $sequence . $this->reversePrimer;
        }

        return new NucleotideSequence($sequence);
    }

    /**
     * Decode NucleotideSequence back to original binary string
     */
    public function decode(NucleotideSequence|string $input, bool $hasPrimers = true): string
    {
        $seq = $input instanceof NucleotideSequence ? $input->getSequence() : strtoupper(trim($input));

        if ($hasPrimers) {
            $fLen = strlen($this->forwardPrimer);
            $rLen = strlen($this->reversePrimer);

            if (strlen($seq) >= $fLen + $rLen) {
                $seq = substr($seq, $fLen, strlen($seq) - $fLen - $rLen);
            }
        }

        $rem = strlen($seq) % 6;
        if ($rem !== 0) {
            $seq = substr($seq, 0, strlen($seq) - $rem);
        }

        $prevBase = 'A'; // Seed nucleotide
        $bytes = [];
        $chars = str_split($seq);
        $totalChars = count($chars);

        for ($i = 0; $i < $totalChars; $i += 6) {
            $byteVal = 0;
            $power = 1;

            for ($j = 0; $j < 6; $j++) {
                $currentBase = $chars[$i + $j] ?? 'A';
                $trit = array_search($currentBase, self::ROTATION_TABLE[$prevBase] ?? ['C', 'G', 'T'], true);

                if ($trit === false) {
                    $trit = 0;
                }

                $byteVal += $trit * $power;
                $power *= 3;
                $prevBase = $currentBase;
            }

            $bytes[] = $byteVal % 256;
        }

        return pack('C*', ...$bytes);
    }

    public function getForwardPrimer(): string
    {
        return $this->forwardPrimer;
    }

    public function getReversePrimer(): string
    {
        return $this->reversePrimer;
    }
}
