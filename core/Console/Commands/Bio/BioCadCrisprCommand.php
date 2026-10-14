<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD CLI
 *
 * Command to screen DNA sequence for CRISPR endonuclease PAM motifs and design candidate gRNAs.
 *
 * @package    NetFusion\Core\Console\Commands\Bio
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Bio;

use NetFusion\Core\Bio\Enums\CasVariant;
use NetFusion\Core\Console\Command;

class BioCadCrisprCommand extends Command
{
    protected string $name = 'biocad:crispr';

    protected string $signature = 'biocad:crispr {sequence? : Target DNA sequence} {--cas=SpCas9 : Cas variant (SpCas9, SaCas9, Cas12a_AsCpf1, Cas13a)} {--limit=5 : Maximum guide candidates}';

    protected string $description = 'Screen DNA sequence and design high-specificity CRISPR guide RNAs';

    public function handle(): int
    {
        $manager = biocad();

        $seq = (string) ($this->argument(0) ?: 'ATGCGATCGATCGATCGATCGATCGATCGATCGGATCGATCGATCGT');
        $casName = (string) ($this->option('cas') ?: 'SpCas9');
        $limit = (int) ($this->option('limit') ?: 5);

        $variant = CasVariant::tryFrom($casName) ?? CasVariant::SpCas9;

        $this->info("Scanning DNA Target for CRISPR Guide RNAs...");
        $this->line(sprintf("Target Length: %d bp | Cas Variant: %s", strlen($seq), $variant->value));
        $this->line(sprintf("Spacer Length: %d nt | PAM Pattern: %s", $variant->spacerLength(), $variant->pamPattern()));

        $guides = $manager->designCrisprGuides($seq, $variant);

        if (empty($guides)) {
            $this->warn("No valid CRISPR target loci found matching the specified PAM requirements.");
            return 0;
        }

        $this->info(sprintf("Found %d candidate guide RNAs (showing top %d):", count($guides), min($limit, count($guides))));
        $this->line("-------------------------------------------------------------------------------");
        $this->line(sprintf("%-5s | %-24s | %-6s | %-6s | %-8s | %-6s | %-8s", "Rank", "Spacer (5'->3')", "PAM", "Strand", "Position", "GC%", "Score"));
        $this->line("-------------------------------------------------------------------------------");

        $rank = 1;
        foreach (array_slice($guides, 0, $limit) as $guide) {
            $this->line(sprintf(
                "%-5d | %-24s | %-6s | %-6s | %-8d | %-5.1f%% | %-8.1f",
                $rank++,
                $guide['spacer'],
                $guide['pam'],
                $guide['strand'],
                $guide['position'],
                $guide['gc'],
                $guide['on_target_score']
            ));
        }
        $this->line("-------------------------------------------------------------------------------");

        return 0;
    }
}
