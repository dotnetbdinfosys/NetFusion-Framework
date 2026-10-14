<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD CLI
 *
 * Command to optimize coding sequences for target host organisms using codon bias adaptation.
 *
 * @package    NetFusion\Core\Console\Commands\Bio
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Bio;

use NetFusion\Core\Bio\Enums\OrganismHost;
use NetFusion\Core\Console\Command;

class BioCadOptimizeCommand extends Command
{
    protected string $name = 'biocad:optimize';

    protected string $signature = 'biocad:optimize {sequence? : Coding sequence (CDS)} {--host=e_coli : Host organism (e_coli, s_cerevisiae, h_sapiens, cho_cells)}';

    protected string $description = 'Optimize coding sequence for host organism codon usage and maximize CAI';

    public function handle(): int
    {
        $manager = biocad();

        $seq = (string) ($this->argument(0) ?: 'ATGGCCATGGCGCCCAGAACCTGAGTA');
        $hostName = (string) ($this->option('host') ?: 'e_coli');

        $host = OrganismHost::tryFrom($hostName) ?? OrganismHost::E_COLI;

        $this->info("Initiating Codon Adaptation Index (CAI) Optimization...");
        $this->line(sprintf("Target Host : %s (Optimal Temp: %.1f C)", $host->value, $host->optimalTemperature()));
        $this->line(sprintf("Original CDS: %s (Length: %d bp)", $seq, strlen($seq)));

        $result = $manager->optimizeCodons($seq, $host);

        $this->info("=== Codon Optimization Summary ===");
        $this->line(sprintf("Original CAI   : %.4f", $result['original_cai']));
        $this->line(sprintf("Optimized CAI  : %.4f", $result['optimized_cai']));
        $this->line(sprintf("Codon Mutations: %d substitutions", $result['substitutions']));
        $this->line(sprintf("Optimized Seq  : %s", $result['optimized']->getSequence()));

        return 0;
    }
}
