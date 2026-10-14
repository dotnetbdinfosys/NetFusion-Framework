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

class SagaShowCommand extends Command
{
    protected string $name = 'saga:show';
    protected string $description = 'Inspect a specific Saga instance and view its detailed step audit trail';

    public function handle(): int
    {
        $sagaId = (string)$this->argument(0);
        if (empty($sagaId)) {
            $this->error("Usage: php netfusion saga:show <saga_id>");
            return 1;
        }

        /** @var SagaManager $manager */
        $manager = $this->app->make(SagaManager::class);

        try {
            $instance = $manager->store()->find($sagaId);
            if ($instance === null) {
                $this->error("Saga instance [{$sagaId}] not found in store.");
                return 1;
            }

            $this->info("=================== Saga Execution Details ===================");
            $this->line("Saga ID     : " . $instance->id);
            $this->line("Saga Name   : " . $instance->sagaName);
            $this->line("State       : " . $instance->state->value);
            $this->line("Duration    : " . $instance->getExecutionDurationMs() . " ms");
            $this->line("Started At  : " . date('Y-m-d H:i:s', (int)$instance->startedAt));
            if ($instance->completedAt) {
                $this->line("Completed At: " . date('Y-m-d H:i:s', (int)$instance->completedAt));
            }
            if ($instance->error) {
                $this->error("Error       : " . $instance->error);
            }

            $this->line("\n--- Context Payload ---");
            $this->line(json_encode($instance->context->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $this->line("\n--- Step Audit Trail ---");
            if (empty($instance->stepResults)) {
                $this->line("No step results recorded.");
            } else {
                foreach ($instance->stepResults as $stepName => $result) {
                    $status = $result->success ? "<info>[SUCCESS]</info>" : "<error>[FAILED]</error>";
                    $compensated = $result->compensated ? " <comment>(Compensated)</comment>" : "";
                    $this->line(sprintf("  • %-25s %s (%0.2f ms)%s", $stepName, $status, $result->executionTimeMs, $compensated));
                    if ($result->error) {
                        $this->line("    Error: " . $result->error);
                    }
                    if ($result->result !== null) {
                        $resStr = is_string($result->result) ? $result->result : json_encode($result->result);
                        $this->line("    Output: " . substr($resStr, 0, 100));
                    }
                }
            }
            $this->line("=============================================================");

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to show Saga: " . $e->getMessage());
            return 1;
        }
    }
}
