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
use NetFusion\Core\Scheduling\SchedulerManager;

class ScheduleDAGCommand extends Command
{
    protected string $name = 'schedule:dag';
    protected string $description = 'Execute a registered DAG workflow pipeline';

    public function handle(): int
    {
        $dagName = $this->argument(0) ?? 'default';

        $this->info("Executing DAG Workflow Pipeline: [{$dagName}]");

        try {
            $scheduler = function_exists('scheduler') ? scheduler() : new SchedulerManager();
            $dag = $scheduler->getDAG($dagName);

            if ($dag === null) {
                $this->error("DAG workflow '{$dagName}' is not registered.");
                return 1;
            }

            $result = $scheduler->runDAG($dagName);

            $this->line(str_repeat('-', 60));
            $this->info("Workflow Status: " . $result['status']);
            $this->info("Execution Time : " . round($result['duration'] * 1000, 2) . "ms");
            $this->line(str_repeat('-', 60));

            foreach ($result['nodes'] as $nodeId => $node) {
                $statusColor = $node['state'] === 'COMPLETED' ? 'info' : ($node['state'] === 'SKIPPED' ? 'comment' : 'error');
                $this->line(sprintf(" - [%s] Node '%s' (%s) in %.2fms", $node['state'], $node['id'], $node['name'], $node['duration'] * 1000));
                if ($node['error']) {
                    $this->error("    Error: " . $node['error']);
                }
            }

            return $result['status'] === 'FAILED' ? 1 : 0;
        } catch (\Throwable $e) {
            $this->error("Failed to execute DAG: " . $e->getMessage());
            return 1;
        }
    }
}
