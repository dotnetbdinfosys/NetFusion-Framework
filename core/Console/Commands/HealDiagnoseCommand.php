<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/HealDiagnoseCommand.php
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
use NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\HealthSignal;

class HealDiagnoseCommand extends Command
{
    protected string $signature = 'heal:diagnose {service? : Target microservice name} {--metric=memory_usage : Anomaly metric name} {--val=98.5 : Observed value}';
    protected string $description = 'Analyze telemetry metrics and diagnose root cause of anomalies';

    public function handle(): int
    {
        $service = (string)($this->argument(0) ?: 'order-service');
        $metric  = (string)($this->option('metric') ?: 'memory_usage');
        $val     = (float)($this->option('val') ?: 98.5);

        $mgr = $this->app->has('selfhealing')
            ? $this->app->make('selfhealing')
            : SelfHealingManager::getInstance();

        // Feed baseline signals
        for ($i = 0; $i < 10; $i++) {
            $mgr->recordSignal($service, $metric, 20.0 + ($i % 3));
        }

        // Feed anomalous spike
        $event = $mgr->recordSignal($service, $metric, $val);

        if ($event === null) {
            $this->warn("Signal did not trigger anomaly threshold (Z-score < 3.0).");
            return 0;
        }

        $report = $mgr->diagnose([$event]);

        $this->info("=== Root-Cause Analysis (RCA) Report ===");
        $this->line("Report ID          : " . $report->getReportId());
        $this->line("Culprit Service    : " . $report->getPrimaryCulpritService());
        $this->line("Primary Hypothesis : " . $report->getPrimaryHypothesis());
        $this->line("Confidence Score   : " . ($report->getConfidence() * 100) . "%");
        $this->line("Severity           : " . strtoupper($report->getSeverity()->value));
        $this->line("Recommended Action : " . strtoupper($report->getRecommendedAction()->value));

        return 0;
    }
}
