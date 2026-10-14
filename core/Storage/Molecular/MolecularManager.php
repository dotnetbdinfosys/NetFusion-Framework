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

namespace NetFusion\Core\Storage\Molecular;

use NetFusion\Core\Storage\Molecular\Codec\ReedSolomonCodec;
use NetFusion\Core\Storage\Molecular\Dna\DnaCodec;
use NetFusion\Core\Storage\Molecular\Dna\OligoPool;
use NetFusion\Core\Storage\Molecular\Holographic\HolographicCrystal;
use NetFusion\Core\Storage\Molecular\Holographic\HolographicPage;
use NetFusion\Core\Storage\Molecular\Vault\ColdVault;

class MolecularManager
{
    protected DnaCodec $dnaCodec;
    protected ReedSolomonCodec $rsCodec;
    /** @var array<string, ColdVault> */
    protected array $vaults = [];
    /** @var array<string, HolographicCrystal> */
    protected array $crystals = [];
    protected static ?self $instance = null;

    public function __construct(
        ?DnaCodec $dnaCodec = null,
        ?ReedSolomonCodec $rsCodec = null
    ) {
        $this->dnaCodec = $dnaCodec ?? new DnaCodec();
        $this->rsCodec = $rsCodec ?? new ReedSolomonCodec();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDnaCodec(): DnaCodec
    {
        return $this->dnaCodec;
    }

    public function getRsCodec(): ReedSolomonCodec
    {
        return $this->rsCodec;
    }

    /**
     * Encode binary string into a synthesized OligoPool with Reed-Solomon parity
     */
    public function encodeDna(string $data, int $chunkSize = 16, int $rsParity = 6): OligoPool
    {
        $pool = new OligoPool('pool_' . bin2hex(random_bytes(4)), $this->dnaCodec, $this->rsCodec);
        $pool->synthesize($data, $chunkSize, $rsParity);
        return $pool;
    }

    /**
     * Decode an OligoPool back to raw binary string with automatic error correction
     */
    public function decodeDna(OligoPool $pool, int $rsParity = 6): string
    {
        return $pool->sequenceAndAssemble($rsParity);
    }

    public function createVault(string $vaultId, string $facility = 'Svalbard_Molecular_Vault'): ColdVault
    {
        $vault = new ColdVault($vaultId, $facility);
        $this->vaults[$vaultId] = $vault;
        return $vault;
    }

    public function getVault(string $vaultId): ?ColdVault
    {
        return $this->vaults[$vaultId] ?? null;
    }

    public function createCrystal(string $crystalId): HolographicCrystal
    {
        $crystal = new HolographicCrystal($crystalId);
        $this->crystals[$crystalId] = $crystal;
        return $crystal;
    }

    public function getCrystal(string $crystalId): ?HolographicCrystal
    {
        return $this->crystals[$crystalId] ?? null;
    }

    /**
     * Store binary payload onto a Holographic Page recorded at given Bragg angle
     */
    public function storeHolographicPage(string $crystalId, string $payload, int $pageIndex, float $braggAngle): HolographicPage
    {
        $crystal = $this->getCrystal($crystalId) ?? $this->createCrystal($crystalId);
        $page = HolographicPage::fromBytes($payload, $pageIndex, $braggAngle);
        $crystal->recordPage($page);
        return $page;
    }

    /**
     * Read and reconstruct a Holographic Page from a Crystal given reference laser angle
     */
    public function readHolographicPage(string $crystalId, float $referenceAngle, int $expectedBytes): ?string
    {
        $crystal = $this->getCrystal($crystalId);
        if ($crystal === null) {
            return null;
        }

        $page = $crystal->reconstructPage($referenceAngle);
        if ($page === null) {
            return null;
        }

        return $page->toBytes($expectedBytes);
    }
}
