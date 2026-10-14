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
use NetFusion\Core\Workflow\WorkflowEngine;

class WorkflowStartCommand extends Command
{
    protected string $signature = 'workflow:start {definition : Workflow definition ID} {payload? : JSON encoded input variables} {--key= : Business key}';
    protected string $description = 'Instantiate and execute a workflow definition with optional input variables';

    public function handle(): int
    {
        /** @var WorkflowEngine $engine */
        $engine = function_exists('workflow_engine') ? workflow_engine() : (function_exists('app') && app()->has('workflow_engine') ? app('workflow_engine') : new WorkflowEngine());

        $definitionId = (string) $this->argument('definition');
        $payloadRaw = $this->argument('payload');
        $businessKey = $this->option('key') ? (string) $this->option('key') : null;

        $variables = [];
        if ($payloadRaw) {
            $decoded = json_decode((string) $payloadRaw, true);
            if (is_array($decoded)) {
                $variables = $decoded;
            } else {
                $this->error("Invalid JSON payload provided.");
                return 1;
            }
        }

        $this->info("Starting workflow definition [{$definitionId}]...");

        try {
            $instance = $engine->start($definitionId, $variables, $businessKey);

            $this->info("Workflow instance started successfully!");
            $this->line("--------------------------------------------------");
            $this->line("Instance ID  : {$instance->getId()}");
            $this->line("Definition   : {$instance->getDefinitionId()}");
            $this->line("Status       : {$instance->getStatus()}");
            $this->line("Business Key : " . ($instance->getBusinessKey() ?? 'N/A'));
            $this->line("Variables    : " . json_encode($instance->getVariables()));
            $this->line("Active Tokens: " . count($instance->getActiveTokens()));
            $this->line("Created At   : {$instance->getCreatedAt()}");
            $this->line("--------------------------------------------------");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to start workflow: " . $e->getMessage());
            return 1;
        }
    }
}
