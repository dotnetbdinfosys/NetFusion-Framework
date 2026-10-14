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
use NetFusion\Core\Saga\SagaManager;
use Throwable;

class SagaListCommand extends Command
{
    protected string $name = 'saga:list';
    protected string $description = 'List recent distributed Saga transaction executions and their state';

    public function handle(): int
    {
        $status = $this->option('status') ? (string)$this->option('status') : null;
        $sagaName = $this->option('name') ? (string)$this->option('name') : null;

        /** @var SagaManager $manager */
        $manager = $this->app->make(SagaManager::class);

        $filters = [];
        if ($status !== null) {
            $filters['status'] = $status;
        }
        if ($sagaName !== null) {
            $filters['saga_name'] = $sagaName;
        }

        try {
            $store = $manager->store();
            $instances = $store->all($filters);

            if (empty($instances)) {
                $this->info("No Saga instances found matching criteria.");
                return 0;
            }

            $this->info("=================== Distributed Saga Instances ===================");
            $this->line(sprintf("%-24s | %-20s | %-12s | %-10s | %s", "Saga ID", "Name", "State", "Duration", "Started At"));
            $this->line(str_repeat("-", 80));

            foreach ($instances as $inst) {
                $startedStr = date('Y-m-d H:i:s', (int)$inst->startedAt);
                $duration = $inst->getExecutionDurationMs() . 'ms';

                $msg = sprintf(
                    "%-24s | %-20s | %-12s | %-10s | %s",
                    $inst->id,
                    substr($inst->sagaName, 0, 20),
                    $inst->state->value,
                    $duration,
                    $startedStr
                );

                if ($inst->state->value === 'COMPLETED') {
                    $this->info($msg);
                } elseif ($inst->state->value === 'COMPENSATED') {
                    $this->warn($msg);
                } elseif ($inst->state->value === 'FAILED') {
                    $this->error($msg);
                } else {
                    $this->line($msg);
                }
            }

            $this->line(str_repeat("-", 80));
            $this->line("Total Instances: " . count($instances));

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to list Sagas: " . $e->getMessage());
            return 1;
        }
    }
}
