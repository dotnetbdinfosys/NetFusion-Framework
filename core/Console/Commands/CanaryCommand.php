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
use NetFusion\Core\Experimentation\EvaluationContext;
use NetFusion\Core\Experimentation\ExperimentManager;

class CanaryCommand extends Command
{
    protected string $signature = 'canary:eval {flag : Canary flag key} {user : User/Subject ID} {--kill : Engage emergency kill switch} {--reset : Reset emergency kill switch}';
    protected string $description = 'Evaluate or manage canary progressive rollout';

    public function handle(): int
    {
        /** @var ExperimentManager $manager */
        $manager = function_exists('app') && app()->has('experiment') ? app('experiment') : new ExperimentManager();

        $flag = (string)$this->argument('flag');
        $user = (string)$this->argument('user');

        if ($this->option('kill')) {
            $manager->killCanary($flag);
            $this->warn("Emergency kill-switch engaged for canary flag '{$flag}'. Traffic dropped to 0%.");
            return 0;
        }

        if ($this->option('reset')) {
            $manager->resetCanary($flag);
            $this->info("Emergency kill-switch reset for canary flag '{$flag}'.");
            return 0;
        }

        $canary = $manager->getCanary($flag);
        if ($canary === null) {
            $this->error("Canary rollout '{$flag}' not found.");
            return 1;
        }

        $context = new EvaluationContext(userId: $user);
        $enabled = $manager->canary($flag, $context);

        $this->info("=== Canary Rollout Evaluation ===");
        $this->line("Flag Key      : {$flag}");
        $this->line("Subject       : {$user}");
        $this->line("Effective Pct : {$canary->getEffectivePercentage()}%");
        $this->line("Kill Switch   : " . ($canary->killSwitchTriggered ? "\033[31mTRIGGERED (OFF)\033[0m" : "\033[32mNORMAL\033[0m"));
        $this->line("Result        : " . ($enabled ? "\033[1;32mENABLED (Canary Variant)\033[0m" : "\033[33mDISABLED (Baseline)\033[0m"));

        return 0;
    }
}
