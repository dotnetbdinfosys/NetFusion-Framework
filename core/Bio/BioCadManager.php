<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Fabric
 *
 * Master orchestrator facade managing sequence design, CRISPR screening, codon bias, and genetic circuits.
 *
 * @package    NetFusion\Core\Bio
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Bio;

use NetFusion\Core\Bio\Circuit\GeneticCircuitSimulator;
use NetFusion\Core\Bio\Circuit\GeneticPart;
use NetFusion\Core\Bio\Codon\CodonOptimizer;
use NetFusion\Core\Bio\Crispr\CrisprEngine;
use NetFusion\Core\Bio\Enums\CasVariant;
use NetFusion\Core\Bio\Enums\OrganismHost;
use NetFusion\Core\Bio\Sequence\DnaSequence;

class BioCadManager
{
    protected CrisprEngine $crispr;
    protected CodonOptimizer $codonOptimizer;
    protected GeneticCircuitSimulator $circuitSimulator;

    /**
     * Standard biological parts registry.
     *
     * @var array<string, GeneticPart>
     */
    protected array $parts = [];

    public function __construct(
        ?CrisprEngine $crispr = null,
        ?CodonOptimizer $codonOptimizer = null,
        ?GeneticCircuitSimulator $circuitSimulator = null
    ) {
        $this->crispr           = $crispr ?? new CrisprEngine();
        $this->codonOptimizer   = $codonOptimizer ?? new CodonOptimizer();
        $this->circuitSimulator = $circuitSimulator ?? new GeneticCircuitSimulator();
    }

    /**
     * Register a standard biological genetic component into the repository.
     */
    public function registerPart(GeneticPart $part): void
    {
        $this->parts[$part->getPartId()] = $part;
    }

    public function getPart(string $partId): ?GeneticPart
    {
        return $this->parts[$partId] ?? null;
    }

    /**
     * @return array<string, GeneticPart>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Design CRISPR candidate guide RNAs targeting a DNA locus.
     */
    public function designCrisprGuides(string|DnaSequence $target, CasVariant $variant = CasVariant::SpCas9): array
    {
        $dna = $target instanceof DnaSequence ? $target : new DnaSequence($target);
        return $this->crispr->designGuideRnas($dna, $variant);
    }

    /**
     * Compute MIT Off-Target cleavage probability score.
     */
    public function scoreOffTarget(string $spacer, string $offTargetProtospacer): float
    {
        return $this->crispr->calculateOffTargetScore($spacer, $offTargetProtospacer);
    }

    /**
     * Optimize coding sequence for target host organism and return CAI metrics.
     */
    public function optimizeCodons(string|DnaSequence $codingSequence, OrganismHost $host = OrganismHost::E_COLI): array
    {
        $dna = $codingSequence instanceof DnaSequence ? $codingSequence : new DnaSequence($codingSequence);
        return $this->codonOptimizer->optimize($dna, $host);
    }

    /**
     * Simulate Gardner et al. Genetic Toggle Switch bistability.
     */
    public function simulateToggle(float $initU = 2.0, float $initV = 0.2): array
    {
        return $this->circuitSimulator->simulateToggleSwitch($initU, $initV);
    }

    /**
     * Simulate synthetic Repressilator cyclic genetic oscillator.
     */
    public function simulateRepressilator(float $alpha = 50.0): array
    {
        return $this->circuitSimulator->simulateRepressilator($alpha);
    }

    public function getCrisprEngine(): CrisprEngine
    {
        return $this->crispr;
    }

    public function getCodonOptimizer(): CodonOptimizer
    {
        return $this->codonOptimizer;
    }

    public function getCircuitSimulator(): GeneticCircuitSimulator
    {
        return $this->circuitSimulator;
    }
}
