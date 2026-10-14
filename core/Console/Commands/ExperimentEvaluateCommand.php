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

class ExperimentEvaluateCommand extends Command
{
    protected string $signature = 'experiment:eval {key : Experiment key} {user : User/Subject ID} {--attr= : JSON attributes string}';
    protected string $description = 'Evaluate variant assignment for a specific user/context';

    public function handle(): int
    {
        /** @var ExperimentManager $manager */
        $manager = function_exists('app') && app()->has('experiment') ? app('experiment') : new ExperimentManager();

        $key = (string)$this->argument('key');
        $user = (string)$this->argument('user');
        $attrJson = $this->option('attr') ? (string)$this->option('attr') : '{}';
        $attributes = (array)json_decode($attrJson, true);

        $context = new EvaluationContext(userId: $user, attributes: $attributes);
        $variant = $manager->evaluate($key, $context);

        if ($variant === null) {
            $this->error("Experiment '{$key}' not found or no variant assigned.");
            return 1;
        }

        $this->info("=== Experiment Evaluation Result ===");
        $this->line("Experiment       : {$key}");
        $this->line("Subject (User ID): {$user}");
        $this->line("Assigned Variant : \033[1;32m{$variant->name}\033[0m (Weight: {$variant->weight}%)");
        if (!empty($variant->payload)) {
            $this->line("Payload          : " . json_encode($variant->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }
}
