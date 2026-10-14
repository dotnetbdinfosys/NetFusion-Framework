<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/NeuromorphicBenchmarkCommand.php
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
use NetFusion\Core\AI\Neuromorphic\NeuromorphicManager;

class NeuromorphicBenchmarkCommand extends Command
{
    protected string $signature = 'neuromorphic:benchmark {--neurons=50 : Neuron population} {--timesteps=50 : Simulation steps}';
    protected string $description = 'Benchmark neuromorphic Synaptic Operations per Second (SOPs) and spike sparsity';

    public function handle(): int
    {
        $neurons   = (int)($this->option('neurons') ?: 50);
        $timesteps = (int)($this->option('timesteps') ?: 50);

        $mgr = $this->app->has('neuromorphic')
            ? $this->app->make('neuromorphic')
            : NeuromorphicManager::getInstance();

        $res = $mgr->benchmark($neurons, $timesteps);

        $this->info("=== Neuromorphic Computing Benchmark ===");
        $this->line("Neuron Population  : " . $res['neurons']);
        $this->line("Temporal Steps     : " . $res['timesteps']);
        $this->line("Synaptic Operations: " . $res['total_sops'] . " SOPs");
        $this->line("Elapsed Duration   : " . $res['elapsed_ms'] . " ms");
        $this->line("Throughput Rate    : " . $res['sops_per_second'] . " SOPs/sec");
        $this->line("Spike Sparsity     : " . $res['spike_sparsity'] . "% energy efficiency");

        return 0;
    }
}
