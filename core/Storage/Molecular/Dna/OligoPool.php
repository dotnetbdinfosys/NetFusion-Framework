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

use JsonSerializable;
use NetFusion\Core\Storage\Molecular\Codec\ReedSolomonCodec;
use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class OligoPool implements JsonSerializable
{
    /** @var NucleotideSequence[] */
    protected array $oligos = [];
    protected DnaCodec $dnaCodec;
    protected ReedSolomonCodec $rsCodec;

    public function __construct(
        protected string $poolId,
        ?DnaCodec $dnaCodec = null,
        ?ReedSolomonCodec $rsCodec = null
    ) {
        $this->dnaCodec = $dnaCodec ?? new DnaCodec();
        $this->rsCodec = $rsCodec ?? new ReedSolomonCodec();
    }

    public function getPoolId(): string
    {
        return $this->poolId;
    }

    /**
     * Create an Oligo pool by chunking binary data, adding index + RS parity, and encoding to DNA
     *
     * @param string $data Raw binary data
     * @param int $chunkSize Number of payload bytes per oligo (e.g. 16 or 32)
     * @param int $rsParitySymbols Number of Reed-Solomon parity symbols per oligo
     */
    public function synthesize(string $data, int $chunkSize = 16, int $rsParitySymbols = 6): void
    {
        $this->oligos = [];
        $chunks = str_split($data, $chunkSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            // Header: index (2 bytes) + total (2 bytes) + payload
            $header = pack('n2', $index, $totalChunks);
            $packetBytes = array_values(unpack('C*', $header . $chunk));

            // Reed-Solomon encode the packet
            $codeword = $this->rsCodec->encode($packetBytes, $rsParitySymbols);
            $codewordRaw = pack('C*', ...$codeword);

            // DNA rotational encode with primers
            $dnaSeq = $this->dnaCodec->encode($codewordRaw, true);
            $this->oligos[] = $dnaSeq;
        }
    }

    /**
     * Sequence and reconstruct the original binary data from the oligo pool
     *
     * @param int $rsParitySymbols Number of Reed-Solomon parity symbols
     * @return string Original binary payload
     */
    public function sequenceAndAssemble(int $rsParitySymbols = 6): string
    {
        if (empty($this->oligos)) {
            throw new MolecularStorageException("Oligo pool is empty; cannot sequence.");
        }

        $assembled = [];
        $totalExpected = null;

        foreach ($this->oligos as $oligo) {
            try {
                $rawCodeword = $this->dnaCodec->decode($oligo, true);
                $codewordBytes = array_values(unpack('C*', $rawCodeword));

                // Reed-Solomon error correction
                $correctedBytes = $this->rsCodec->decode($codewordBytes, $rsParitySymbols);
                $packet = pack('C*', ...$correctedBytes);

                if (strlen($packet) < 4) {
                    continue;
                }

                $meta = unpack('nindex/ntotal', substr($packet, 0, 4));
                $index = $meta['index'];
                $totalExpected = $meta['total'];
                $payload = substr($packet, 4);

                $assembled[$index] = $payload;
            } catch (\Throwable $e) {
                // Skip severely degraded oligo if uncorrectable
                continue;
            }
        }

        if ($totalExpected === null || count($assembled) < $totalExpected) {
            $got = count($assembled);
            throw new MolecularStorageException("Missing oligo fragments: assembled {$got} of {$totalExpected} chunks.");
        }

        ksort($assembled);
        return implode('', $assembled);
    }

    /**
     * @return NucleotideSequence[]
     */
    public function getOligos(): array
    {
        return $this->oligos;
    }

    public function addOligo(NucleotideSequence $oligo): void
    {
        $this->oligos[] = $oligo;
    }

    public function count(): int
    {
        return count($this->oligos);
    }

    public function toArray(): array
    {
        $seqs = [];
        foreach ($this->oligos as $o) {
            $seqs[] = [
                'sequence' => $o->getSequence(),
                'length' => $o->length(),
                'gcContent' => $o->getGcContent(),
                'maxHomopolymer' => $o->getMaxHomopolymerRun(),
            ];
        }

        return [
            'poolId' => $this->poolId,
            'totalOligos' => count($this->oligos),
            'oligos' => $seqs,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
