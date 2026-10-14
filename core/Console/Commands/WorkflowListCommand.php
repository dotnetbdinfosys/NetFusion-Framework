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

class WorkflowListCommand extends Command
{
    protected string $signature = 'workflow:list {--status= : Filter by status (e.g. RUNNING, SUSPENDED, COMPLETED, FAILED)} {--def= : Filter by definition ID} {--limit=20 : Maximum records to show}';
    protected string $description = 'List workflow instances and their current lifecycle statuses';

    public function handle(): int
    {
        /** @var WorkflowEngine $engine */
        $engine = function_exists('workflow_engine') ? workflow_engine() : (function_exists('app') && app()->has('workflow_engine') ? app('workflow_engine') : new WorkflowEngine());

        $status = $this->option('status') ? strtoupper((string) $this->option('status')) : null;
        $defId = $this->option('def') ? (string) $this->option('def') : null;
        $limit = (int) $this->option('limit');

        $instances = $engine->getStore()->listInstances($status, $defId, $limit);

        $this->info("==========================================================================================");
        $this->info("                             Workflow Instances Registry");
        $this->info("==========================================================================================");

        if (empty($instances)) {
            $this->line("No workflow instances found matching the criteria.");
            return 0;
        }

        $headers = ['Instance ID', 'Definition', 'Status', 'Business Key', 'Created At'];
        $rows = [];

        foreach ($instances as $inst) {
            $rows[] = [
                $inst->getId(),
                $inst->getDefinitionId(),
                $inst->getStatus(),
                $inst->getBusinessKey() ?? '-',
                $inst->getCreatedAt(),
            ];
        }

        $this->table($headers, $rows);
        $this->info("Total instances displayed: " . count($instances));
        return 0;
    }
}