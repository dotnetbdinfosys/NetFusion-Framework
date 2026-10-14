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

namespace NetFusion\Core\Storage\Molecular\Vault;

use JsonSerializable;
use NetFusion\Core\Storage\Molecular\Dna\NucleotideSequence;
use NetFusion\Core\Storage\Molecular\Dna\OligoPool;
use NetFusion\Core\Storage\Molecular\Enums\DecayCondition;
use NetFusion\Core\Storage\Molecular\Enums\Nucleotide;
use NetFusion\Core\Storage\Molecular\Enums\StorageMedium;
use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class ColdVault implements JsonSerializable
{
    /** @var array<string, array{medium: StorageMedium, data: mixed, timestamp: float, condition: DecayCondition}> */
    protected array $vaultEntries = [];

    public function __construct(
        protected string $vaultId,
        protected string $facility = 'Svalbard_Molecular_Vault'
    ) {
    }

    public function getVaultId(): string
    {
        return $this->vaultId;
    }

    public function getFacility(): string
    {
        return $this->facility;
    }

    /**
     * Deposit an OligoPool into the cold vault
     */
    public function depositDna(string $archiveKey, OligoPool $pool, DecayCondition $condition = DecayCondition::CRYOGENIC): void
    {
        $this->vaultEntries[$archiveKey] = [
            'medium' => StorageMedium::DNA_OLIGO,
            'data' => $pool,
            'timestamp' => microtime(true),
            'condition' => $condition,
        ];
    }

    /**
     * Retrieve an OligoPool from vault
     */
    public function retrieveDna(string $archiveKey): ?OligoPool
    {
        $entry = $this->vaultEntries[$archiveKey] ?? null;
        if ($entry === null || $entry['medium'] !== StorageMedium::DNA_OLIGO) {
            return null;
        }
        return $entry['data'];
    }

    /**
     * Simulate chemical base decay / point mutations on a stored DNA pool
     *
     * @param string $archiveKey
     * @param float $errorRate e.g. 0.02 (2% mutated bases)
     * @return int Total number of mutated bases
     */
    public function simulateDecay(string $archiveKey, float $errorRate = 0.02): int
    {
        $pool = $this->retrieveDna($archiveKey);
        if ($pool === null) {
            throw new MolecularStorageException("Archive key not found in vault: {$archiveKey}");
        }

        $mutatedCount = 0;
        $bases = ['A', 'C', 'G', 'T'];

        foreach ($pool->getOligos() as $index => $oligo) {
            $seq = $oligo->getSequence();
            $len = strlen($seq);
            $chars = str_split($seq);
            $oligoMutations = 0;

            for ($i = 0; $i < $len; $i++) {
                if ($oligoMutations < 2 && (mt_rand(1, 1000) / 1000.0) < $errorRate) {
                    // Mutate to a different base
                    $orig = $chars[$i];
                    $avail = array_diff($bases, [$orig]);
                    $chars[$i] = $avail[array_rand($avail)];
                    $mutatedCount++;
                    $oligoMutations++;
                }
            }

            // Replace with mutated sequence in pool
            $reflection = new \ReflectionClass($pool);
            $prop = $reflection->getProperty('oligos');
            $oligos = $prop->getValue($pool);
            $oligos[$index] = new NucleotideSequence(implode('', $chars));
            $prop->setValue($pool, $oligos);
        }

        return $mutatedCount;
    }

    /**
     * Estimate archival half-life in years based on storage temperature condition
     */
    public function getEstimatedHalfLifeYears(DecayCondition $condition): int
    {
        return match ($condition) {
            DecayCondition::CRYOGENIC => 1000000, // 1 million years in liquid nitrogen
            DecayCondition::ROOM_TEMPERATURE => 500, // ~500 years in silica encapsulation
            DecayCondition::ACCELERATED_HEAT => 5, // Accelerated test (e.g. 70°C oven)
        };
    }

    public function getEntriesCount(): int
    {
        return count($this->vaultEntries);
    }

    public function toArray(): array
    {
        return [
            'vaultId' => $this->vaultId,
            'facility' => $this->facility,
            'totalArchives' => count($this->vaultEntries),
            'archives' => array_keys($this->vaultEntries),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
