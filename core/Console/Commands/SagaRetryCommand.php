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

class SagaRetryCommand extends Command
{
    protected string $name = 'saga:retry';
    protected string $description = 'Retry a failed or paused Saga transaction';

    public function handle(): int
    {
        $sagaId = (string)$this->argument(0);
        if (empty($sagaId)) {
            $this->error("Usage: php netfusion saga:retry <saga_id>");
            return 1;
        }

        /** @var SagaManager $manager */
        $manager = $this->app->make(SagaManager::class);

        $this->info("Retrying Saga [{$sagaId}]...");

        try {
            $instance = $manager->retry($sagaId);

            if ($instance->isSuccess()) {
                $this->info("Saga [{$sagaId}] successfully completed after retry!");
            } else {
                $this->error("Saga [{$sagaId}] ended with state: " . $instance->state->value);
                if ($instance->error) {
                    $this->error("Error: " . $instance->error);
                }
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to retry Saga: " . $e->getMessage());
            return 1;
        }
    }
}
