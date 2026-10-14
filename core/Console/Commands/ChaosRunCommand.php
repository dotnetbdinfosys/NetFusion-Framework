<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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
use NetFusion\Core\Chaos\ChaosManager;
use NetFusion\Core\Chaos\Experiment\ChaosExperiment;
use NetFusion\Core\Chaos\Injectors\LatencyInjector;

/**
 * Class ChaosRunCommand
 *
 * CLI: php netfusion chaos:run [experiment_name] [--force] [--format=text|json]
 */
class ChaosRunCommand extends Command
{
    protected string $name        = 'chaos:run';
    protected string $description = 'Execute a Chaos Engineering GameDay experiment and evaluate system resiliency';

    public function handle(array $args = []): int
    {
        $expName = null;
        if (isset($args[0]) && !str_starts_with((string) $args[0], '--')) {
            $expName = (string) $args[0];
        } else {
            $arg0 = $this->argument(0);
            if ($arg0 !== null && !str_starts_with($arg0, '--')) {
                $expName = $arg0;
            }
        }

        $force = isset($args['force']) || in_array('--force', $args, true) || $this->hasOption('force');
        $format = is_string($args['format'] ?? null) ? $args['format'] : (string) $this->option('format', 'text');
        if (in_array('--format=json', $args, true)) {
            $format = 'json';
        }

        try {
            $chaos = ChaosManager::getInstance();

            if ($expName === null || $expName === '') {
                // Default benchmark experiment
                $exp = new ChaosExperiment('Standard Resiliency Baseline Drill', 'Baseline latency and fault tolerance validation');
                $exp->addInjector(new LatencyInjector('cli_probe_latency', 50, 10, 1.0, true));
                $exp->getPolicy()->setAllowedEnvironments(['development', 'testing', 'staging', 'production']);
                $exp->setAction(function () {
                    // Simulated critical service operation
                    return ['status' => 'ok', 'processed_records' => 100];
                });
            } else {
                $exp = $chaos->getExperiment($expName);
                if ($exp === null) {
                    $this->error("Chaos experiment '{$expName}' not found in registry.");
                    return 1;
                }
            }

            $context = [
                'force' => $force,
                'environment' => 'staging',
                'pre_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 45.0, 'success_rate' => 100.0],
                'post_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 50.0, 'success_rate' => 100.0],
            ];

            $result = $chaos->getOrchestrator()->run($exp, $context);

            if ($format === 'json') {
                echo $result->toJson() . "\n";
                return $result->isSuccess() ? 0 : 1;
            }

            echo "\n=== NetFusion Chaos Engineering GameDay Result ===\n";
            echo "====================================================\n";
            echo "Experiment : " . $result->getExperimentName() . " (" . $result->getExperimentId() . ")\n";
            echo "Status     : " . $result->getStatus() . "\n";
            echo "Score      : " . $result->getResiliencyScore() . " / 100 (" . $chaos->getScorer()->getGrade($result->getResiliencyScore()) . ")\n";
            echo "Duration   : " . $result->getDurationMs() . " ms\n";
            echo "Pre-State  : " . ($result->isSteadyStatePre() ? 'HEALTHY' : 'UNHEALTHY') . "\n";
            echo "Post-State : " . ($result->isSteadyStatePost() ? 'RECOVERED' : 'DEGRADED') . "\n";
            echo "Violations : " . count($result->getViolations()) . "\n";

            if ($result->getAbortReason() !== null) {
                echo "Abort Note : " . $result->getAbortReason() . "\n";
            }

            echo "\nRecommendations:\n";
            foreach ($chaos->getScorer()->generateRecommendations($result) as $rec) {
                echo " - " . $rec . "\n";
            }
            echo "\n";

            return $result->isSuccess() ? 0 : 1;
        } catch (\Throwable $e) {
            $this->error("Chaos run failed: " . $e->getMessage());
            return 1;
        }
    }
}
