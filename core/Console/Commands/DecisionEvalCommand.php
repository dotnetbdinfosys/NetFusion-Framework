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
use NetFusion\Core\RuleEngine\RuleEngineManager;

class DecisionEvalCommand extends Command
{
    protected string $name = 'decision:eval';
    protected string $signature = 'decision:eval {table : Decision table name} {context? : JSON context string}';
    protected string $description = 'Evaluate a DMN 1.3 Decision Table against a JSON input context';

    public function handle(): int
    {
        /** @var RuleEngineManager $manager */
        $manager = function_exists('rule_engine') ? rule_engine() : (function_exists('app') && app()->has('rule_engine') ? app('rule_engine') : new RuleEngineManager());

        $tableName = (string) $this->argument('table');
        $rawContext = (string) ($this->argument('context') ?? '{}');
        $context = json_decode($rawContext, true);

        if (!is_array($context)) {
            $this->error("Invalid JSON context provided: {$rawContext}");
            return 1;
        }

        try {
            $result = $manager->evaluateDecision($tableName, $context);

            $this->info("==========================================================================================");
            $this->info("             DMN 1.3 Decision Evaluation: {$tableName} ({$result->getHitPolicy()})");
            $this->info("==========================================================================================");
            $this->line("Evaluation Time: " . round($result->getEvaluationTimeMs(), 3) . "ms");
            $this->line("Matched Rule Indices: " . implode(', ', $result->getMatchedRuleIndices()) ?: 'None');
            $this->line("Output Result:");
            $this->line(json_encode($result->getOutputs(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        } catch (\Throwable $e) {
            $this->error("Evaluation error: {$e->getMessage()}");
            return 1;
        }
    }
}
