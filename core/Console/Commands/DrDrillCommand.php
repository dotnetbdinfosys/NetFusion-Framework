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

/**
 * Class DrDrillCommand
 *
 * CLI: php netfusion dr:drill [--target=<region>]
 */
class DrDrillCommand extends Command
{
    protected string $name        = 'dr:drill';
    protected string $description = 'Run a non-disruptive sandbox Disaster Recovery drill and verify SLA compliance';

    public function handle(array $args = []): int
    {
        $targetRegion = $this->option($args, 'target', 'eu-west-1');

        try {
            $dr = dr();
            echo "Running Disaster Recovery Drill simulation for target region '{$targetRegion}'...\n";
            $drill = $dr->runDrill($targetRegion);

            echo "\n=== NetFusion Disaster Recovery Drill Report ===\n";
            echo "=================================================\n";
            echo "Drill ID        : " . $drill->drillId . "\n";
            echo "Target Region   : " . $drill->targetRegion . "\n";
            echo "Status          : " . $drill->status . "\n";
            echo "Measured RTO    : " . $drill->rtoSeconds . "s (SLA Target: {$drill->targetRtoSla}s)\n";
            echo "Measured RPO    : " . $drill->rpoSeconds . "s (SLA Target: {$drill->targetRpoSla}s)\n";
            echo "SLA Compliance  : " . ($drill->isSlaCompliant() ? 'PASSED (Compliant)' : 'BREACHED') . "\n\n";

            echo "Execution Steps:\n";
            foreach ($drill->steps as $idx => $step) {
                $icon = $step['success'] ? '✓' : '✗';
                echo sprintf("  %d. [%s] %-25s: %s\n", $idx + 1, $icon, $step['step'], $step['message']);
            }

            echo "\n";
            return $drill->isSlaCompliant() ? 0 : 1;
        } catch (\Throwable $e) {
            $this->error("DR drill execution failed: " . $e->getMessage());
            return 1;
        }
    }
}
