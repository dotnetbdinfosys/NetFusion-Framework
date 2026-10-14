<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Experimentation\ExperimentManager;

class ExperimentReportCommand extends Command
{
    protected string $signature = 'experiment:report {key : Experiment key} {--goal= : Specific goal to analyze} {--alpha=0.05 : Statistical significance threshold}';
    protected string $description = 'Generate statistical significance report and conversion analytics for an experiment';

    public function handle(): int
    {
        /** @var ExperimentManager $manager */
        $manager = function_exists('app') && app()->has('experiment') ? app('experiment') : new ExperimentManager();

        $key = (string)$this->argument('key');
        $goal = $this->option('goal') ? (string)$this->option('goal') : null;
        $alpha = (float)$this->option('alpha');

        $exp = $manager->getExperiment($key);
        if ($exp === null) {
            $this->error("Experiment '{$key}' not found.");
            return 1;
        }

        $results = $manager->getResults($key, $goal, $alpha);

        $this->info("==================================================================");
        $this->info("   A/B Experiment Report: {$key} ({$exp->name})");
        $this->info("==================================================================");
        $this->line("Total Exposures   : {$results['total_exposures']}");
        $this->line("Total Conversions : {$results['total_conversions']}");
        $this->line("Goal Analyzed     : " . ($goal ?? 'All Goals'));
        $this->line("------------------------------------------------------------------");

        $this->info("Variant Breakdown:");
        foreach ($results['variants'] as $vName => $data) {
            $this->line(sprintf(
                " - \033[1;37m%-12s\033[0m: Exposures: %-6d | Conv: %-5d | Rate: %-6.2f%% | Revenue: $%.2f (ARPU: $%.2f)",
                $vName,
                $data['exposures'],
                $data['conversions'],
                $data['conversion_rate_pct'],
                $data['revenue'],
                $data['arpu']
            ));
        }

        if (!empty($results['comparisons'])) {
            $this->line("------------------------------------------------------------------");
            $this->info("Statistical Hypothesis Testing vs Control:");
            foreach ($results['comparisons'] as $tName => $comp) {
                $sigLabel = $comp['is_significant'] ? "\033[32mYES (p < {$alpha})\033[0m" : "\033[33mNO (Not Significant)\033[0m";
                $upliftColor = $comp['relative_uplift_pct'] >= 0 ? "\033[32m" : "\033[31m";
                $this->line(sprintf(
                    " - %s vs %s: Uplift: %s%+.2f%%\033[0m | Z-Score: %+.3f | p-value: %.5f | Confidence: %.1f%% | Significant: %s",
                    $tName,
                    $comp['control'],
                    $upliftColor,
                    $comp['relative_uplift_pct'],
                    $comp['z_score'],
                    $comp['p_value'],
                    $comp['confidence_pct'],
                    $sigLabel
                ));
            }
        }

        $this->line("------------------------------------------------------------------");
        if ($results['winner']) {
            $this->info("Statistically Significant Winner: \033[1;32m{$results['winner']}\033[0m");
        } else {
            $this->line("Statistically Significant Winner: None yet (requires more sample size or no uplift)");
        }
        $this->info("==================================================================");

        return 0;
    }
}
