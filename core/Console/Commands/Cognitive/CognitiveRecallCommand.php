<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture CLI
 *
 * Command to recall declarative chunks with base-level activation decay.
 *
 * @package    NetFusion\Core\Console\Commands\Cognitive
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Cognitive;

use NetFusion\Core\Cognitive\CognitiveManager;
use NetFusion\Core\Console\Command;

class CognitiveRecallCommand extends Command
{
    protected string $signature = 'cognitive:recall
        {--concept=server_metric : Concept type to query}';

    protected string $description = 'Recall fact chunks from declarative memory with ACT-R base-level activations';

    public function handle(): int
    {
        $manager = cognitive();
        $concept = (string) $this->option('concept');


        // Populate sample declarative facts
        $c1 = $manager->learnFact('chunk_mem_01', $concept, ['metric' => 'ram_usage', 'threshold' => '90%']);
        $c2 = $manager->learnFact('chunk_cpu_01', $concept, ['metric' => 'cpu_load', 'threshold' => '85%']);

        // Rehearse c1 twice to elevate activation
        $c1->touch();
        $c1->touch();

        $recalled = $manager->declarative()->retrieve(['metric' => 'ram_usage']);

        $this->info("=== Cognitive Memory Recall: Declarative Memory ===");
        $this->line("Target Concept     : {$concept}");
        $this->line("Recalled Chunk ID  : {$recalled->getId()}");
        $this->line("Access History Count: {$recalled->getAccessCount()} touches");
        $this->line("Base Activation (A): " . round($recalled->getBaseLevelActivation(), 4));
        $this->line("Slots Payload      : " . json_encode($recalled->getSlots()));

        return 0;
    }
}
