<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/MolecularEncodeCommand.php
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
use NetFusion\Core\Storage\Molecular\MolecularManager;

class MolecularEncodeCommand extends Command
{
    protected string $signature = 'molecular:encode {payload : Binary data or string to encode} {--chunk=16 : Payload bytes per oligo} {--parity=6 : Reed-Solomon parity symbols}';
    protected string $description = 'Encode digital data into biological DNA oligonucleotides with Reed-Solomon error correction';

    public function handle(): int
    {
        $payload = (string) $this->argument(0);
        if ($payload === '') {
            $this->error("Payload cannot be empty.");
            return 1;
        }

        $chunkSize = (int) ($this->option('chunk') ?: 16);
        $parity = (int) ($this->option('parity') ?: 6);

        /** @var MolecularManager $manager */
        $manager = $this->app->has('molecular')
            ? $this->app->make('molecular')
            : MolecularManager::getInstance();

        $pool = $manager->encodeDna($payload, $chunkSize, $parity);

        $this->info("=== DNA Molecular Synthesis Complete ===");
        $this->line("Pool Identifier    : " . $pool->getPoolId());
        $this->line("Input Bytes        : " . strlen($payload));
        $this->line("Synthesized Oligos : " . $pool->count());

        $oligos = $pool->getOligos();
        if (!empty($oligos)) {
            $sample = $oligos[0];
            $this->line("Sample Oligo 1     : " . $sample->getSequence());
            $this->line("Oligo Length       : " . $sample->length() . " nt");
            $this->line("GC Content         : " . ($sample->getGcContent() * 100) . "%");
            $this->line("Max Homopolymer    : " . $sample->getMaxHomopolymerRun() . " (Rule: <= 1)");
        }

        return 0;
    }
}
