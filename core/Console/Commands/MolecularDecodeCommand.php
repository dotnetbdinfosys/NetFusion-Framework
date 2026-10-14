<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/MolecularDecodeCommand.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Storage\Molecular\Dna\NucleotideSequence;
use NetFusion\Core\Storage\Molecular\Dna\OligoPool;
use NetFusion\Core\Storage\Molecular\MolecularManager;

class MolecularDecodeCommand extends Command
{
    protected string $signature = 'molecular:decode {sequence : Nucleotide sequence string} {--parity=6 : Reed-Solomon parity symbols}';
    protected string $description = 'Sequence and decode DNA oligonucleotides back into original digital data';

    public function handle(): int
    {
        $seqStr = (string) $this->argument(0);
        if ($seqStr === '') {
            $this->error("Sequence cannot be empty.");
            return 1;
        }

        $parity = (int) ($this->option('parity') ?: 6);

        /** @var MolecularManager $manager */
        $manager = $this->app->has('molecular')
            ? $this->app->make('molecular')
            : MolecularManager::getInstance();

        try {
            $pool = new OligoPool('cli_pool', $manager->getDnaCodec(), $manager->getRsCodec());
            $pool->addOligo(new NucleotideSequence($seqStr));
            $recovered = $manager->decodeDna($pool, $parity);

            $this->info("=== DNA Sequencing Reconstructed ===");
            $this->line("Recovered Plaintext: " . $recovered);
            return 0;
        } catch (\Throwable $e) {
            $this->error("Sequencing failure: " . $e->getMessage());
            return 1;
        }
    }
}
