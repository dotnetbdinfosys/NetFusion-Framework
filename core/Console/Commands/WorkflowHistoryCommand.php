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

class WorkflowHistoryCommand extends Command
{
    protected string $signature = 'workflow:history {instance : Workflow instance ID}';
    protected string $description = 'Inspect activity execution trail and audit log for a workflow instance';

    public function handle(): int
    {
        /** @var WorkflowEngine $engine */
        $engine = function_exists('workflow_engine') ? workflow_engine() : (function_exists('app') && app()->has('workflow_engine') ? app('workflow_engine') : new WorkflowEngine());

        $instanceId = (string) $this->argument('instance');
        $instance = $engine->getInstance($instanceId);

        if (!$instance) {
            $this->error("Workflow instance [{$instanceId}] not found.");
            return 1;
        }

        $history = $engine->getStore()->getActivityHistory($instanceId);
        if (empty($history)) {
            $history = $instance->getHistory();
        }

        $this->info("==========================================================================================");
        $this->info("             Activity Audit Trail: {$instanceId} ({$instance->getDefinitionId()})");
        $this->info("==========================================================================================");
        $this->line("Current Status : {$instance->getStatus()}");
        $this->line("Created At     : {$instance->getCreatedAt()}");
        $this->line("Completed At   : " . ($instance->getCompletedAt() ?? 'N/A'));
        $this->line("Variables      : " . json_encode($instance->getVariables()));
        $this->line("------------------------------------------------------------------------------------------");

        if (empty($history)) {
            $this->line("No activity history records found for this instance.");
            return 0;
        }

        $headers = ['Node ID', 'Type', 'Action', 'Status', 'Duration (ms)', 'Time'];
        $rows = [];

        foreach ($history as $act) {
            $rows[] = [
                $act->getNodeId(),
                $act->getNodeType(),
                $act->getAction(),
                $act->getStatus(),
                number_format($act->getDurationMs(), 2),
                $act->getCreatedAt(),
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
