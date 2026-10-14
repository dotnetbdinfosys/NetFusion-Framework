<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/NeuromorphicInferCommand.php
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

class NeuromorphicInferCommand extends Command
{
    protected string $signature = 'neuromorphic:infer {name? : Network Name} {--features=0.8,0.2,0.9,0.7 : Comma-separated feature vector} {--timesteps=30 : Temporal steps}';
    protected string $description = 'Execute real-time event-driven inference using a Spiking Neural Network';

    public function handle(): int
    {
        $name      = (string)($this->argument(0) ?: 'cortex_classifier');
        $rawFeats  = (string)($this->option('features') ?: '0.8,0.2,0.9,0.7');
        $timesteps = (int)($this->option('timesteps') ?: 30);

        $features = array_map('floatval', explode(',', $rawFeats));

        $mgr = $this->app->has('neuromorphic')
            ? $this->app->make('neuromorphic')
            : NeuromorphicManager::getInstance();

        $res = $mgr->infer($name, $features, $timesteps);

        $this->info("=== Neuromorphic SNN Inference Result ===");
        $this->line("Network Name       : " . $name);
        $this->line("Input Dimension    : " . count($features));
        $this->line("Predicted Class    : Class " . $res['predicted_class']);
        $this->line("Confidence Score   : " . ($res['confidence'] * 100) . "%");
        $this->line("Total Output Spikes: " . $res['total_spikes']);
        $this->line("Synaptic Operations: " . $res['synaptic_operations'] . " SOPs");

        return 0;
    }
}
