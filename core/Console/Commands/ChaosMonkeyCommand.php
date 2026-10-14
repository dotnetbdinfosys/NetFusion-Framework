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

/**
 * Class ChaosMonkeyCommand
 *
 * CLI: php netfusion chaos:monkey [--force] [--format=text|json]
 */
class ChaosMonkeyCommand extends Command
{
    protected string $name        = 'chaos:monkey';
    protected string $description = 'Trigger an autonomous randomized Chaos Monkey strike to validate resiliency';

    public function handle(array $args = []): int
    {
        $force = isset($args['force']) || in_array('--force', $args, true) || $this->hasOption('force');
        $format = is_string($args['format'] ?? null) ? $args['format'] : (string) $this->option('format', 'text');
        if (in_array('--format=json', $args, true)) {
            $format = 'json';
        }

        try {
            $chaos = ChaosManager::getInstance();
            $monkey = $chaos->getMonkey();

            $context = [
                'force_window' => $force,
                'environment' => 'staging',
                'pre_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 40.0, 'success_rate' => 100.0],
                'post_metrics' => ['error_rate' => 0.0, 'p99_latency_ms' => 45.0, 'success_rate' => 100.0],
            ];

            $result = $monkey->attack($context);

            if ($result === null) {
                if ($format === 'json') {
                    echo json_encode(['status' => 'SKIPPED', 'reason' => 'Outside maintenance window or monkey disabled'], JSON_PRETTY_PRINT) . "\n";
                } else {
                    $this->warn("Chaos Monkey strike was SKIPPED: Outside permitted window or monkey disabled. Use --force to override.");
                }
                return 0;
            }

            if ($format === 'json') {
                echo $result->toJson() . "\n";
                return $result->isSuccess() ? 0 : 1;
            }

            echo "\n=== NetFusion Autonomous Chaos Monkey Strike ===\n";
            echo "=================================================\n";
            echo "Target Drill : " . $result->getExperimentName() . "\n";
            echo "Status       : " . $result->getStatus() . "\n";
            echo "Score        : " . $result->getResiliencyScore() . " / 100\n";
            echo "Duration     : " . $result->getDurationMs() . " ms\n";
            echo "Faults Fired : " . count($result->getInjectedFaults()) . "\n\n";

            return $result->isSuccess() ? 0 : 1;
        } catch (\Throwable $e) {
            $this->error("Chaos Monkey execution failed: " . $e->getMessage());
            return 1;
        }
    }
}
