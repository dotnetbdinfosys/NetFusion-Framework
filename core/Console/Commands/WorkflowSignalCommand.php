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

class WorkflowSignalCommand extends Command
{
    protected string $signature = 'workflow:signal {instance : Workflow instance ID} {activity : Activity or node ID to signal} {payload? : JSON payload to resume with}';
    protected string $description = 'Send an external resume signal or complete a waiting activity in a workflow';

    public function handle(): int
    {
        /** @var WorkflowEngine $engine */
        $engine = function_exists('workflow_engine') ? workflow_engine() : (function_exists('app') && app()->has('workflow_engine') ? app('workflow_engine') : new WorkflowEngine());

        $instanceId = (string) $this->argument('instance');
        $activityId = (string) $this->argument('activity');
        $payloadRaw = $this->argument('payload');

        $payload = [];
        if ($payloadRaw) {
            $decoded = json_decode((string) $payloadRaw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } else {
                $this->error("Invalid JSON payload provided.");
                return 1;
            }
        }

        $this->info("Sending signal to instance [{$instanceId}] on activity [{$activityId}]...");

        try {
            $instance = $engine->signal($instanceId, $activityId, $payload);

            $this->info("Signal processed successfully!");
            $this->line("--------------------------------------------------");
            $this->line("Instance ID  : {$instance->getId()}");
            $this->line("New Status   : {$instance->getStatus()}");
            $this->line("Variables    : " . json_encode($instance->getVariables()));
            $this->line("Active Tokens: " . count($instance->getActiveTokens()));
            $this->line("--------------------------------------------------");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to signal workflow instance: " . $e->getMessage());
            return 1;
        }
    }
}
