<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/PhotonicBenchmarkCommand.php
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
use NetFusion\Core\Photonic\PhotonicManager;

class PhotonicBenchmarkCommand extends Command
{
    protected string $signature = 'photonic:benchmark {--dim=8 : Matrix dimension} {--batches=500 : Batch count}';
    protected string $description = 'Benchmark optical tensor processing throughput, latency and TOPS/Watt density';

    public function handle(): int
    {
        $dim = (int) ($this->option('dim') ?: 8);
        $batches = (int) ($this->option('batches') ?: 500);

        /** @var PhotonicManager $manager */
        $manager = $this->app->has('photonic')
            ? $this->app->make('photonic')
            : PhotonicManager::getInstance();

        $bench = $manager->benchmark($dim, $batches);

        $this->info("=== Coherent Photonic Computing Benchmark ===");
        $this->line("Matrix Dimension   : {$dim} x {$dim}");
        $this->line("Batch Iterations   : {$batches}");
        $this->line("Total MAC Ops      : " . number_format($bench['totalOps']));
        $this->line("Optical Latency    : {$bench['opticalLatencyPs']} ps");
        $this->line("Throughput Density : {$bench['topsPerWatt']} TOPS/Watt");
        $this->line("Simulation Speed   : {$bench['gflopsEquivalent']} GFLOPS");
        $this->line("Wallclock Time     : {$bench['elapsedSeconds']} s");

        return 0;
    }
}
