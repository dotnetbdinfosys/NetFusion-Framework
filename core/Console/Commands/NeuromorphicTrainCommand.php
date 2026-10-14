<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/NeuromorphicTrainCommand.php
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
use NetFusion\Core\AI\Neuromorphic\Enums\NeuronModel;

class NeuromorphicTrainCommand extends Command
{
    protected string $signature = 'neuromorphic:train {name? : SNN Network Name} {--epochs=5 : Training Epochs} {--lr=0.05 : Learning Rate}';
    protected string $description = 'Train a spiking neural network on temporal patterns using surrogate delta learning';

    public function handle(): int
    {
        $name   = (string)($this->argument(0) ?: 'cortex_classifier');
        $epochs = (int)($this->option('epochs') ?: 5);
        $lr     = (float)($this->option('lr') ?: 0.05);

        $mgr = $this->app->has('neuromorphic')
            ? $this->app->make('neuromorphic')
            : NeuromorphicManager::getInstance();

        if ($mgr->getNetwork($name) === null) {
            $mgr->createNetwork($name, 4, [
                ['name' => 'cortex_hidden', 'size' => 8, 'model' => NeuronModel::LIF],
                ['name' => 'cortex_output', 'size' => 2, 'model' => NeuronModel::LIF],
            ]);
        }

        // Canonical binary XOR-like temporal dataset
        $dataset = [
            ['input' => [0.1, 0.9, 0.1, 0.2], 'target' => 0],
            ['input' => [0.9, 0.1, 0.8, 0.9], 'target' => 1],
            ['input' => [0.2, 0.8, 0.3, 0.1], 'target' => 0],
            ['input' => [0.8, 0.2, 0.9, 0.7], 'target' => 1],
        ];

        $res = $mgr->train($name, $dataset, $epochs, $lr);

        $this->info("=== Spiking Neural Network Training Completed ===");
        $this->line("Network Name    : " . $name);
        $this->line("Epochs Trained  : " . $res['epochs']);
        $this->line("Final Loss (MSE): " . $res['final_loss']);
        $this->line("Training Accuracy: " . $res['accuracy'] . "%");

        return 0;
    }
}
