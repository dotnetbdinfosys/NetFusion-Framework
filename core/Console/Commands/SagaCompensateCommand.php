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

class SagaCompensateCommand extends Command
{
    protected string $name = 'saga:compensate';
    protected string $description = 'Trigger manual backward compensation rollback for a Saga transaction';

    public function handle(): int
    {
        $sagaId = (string)$this->argument(0);
        if (empty($sagaId)) {
            $this->error("Usage: php netfusion saga:compensate <saga_id>");
            return 1;
        }

        /** @var SagaManager $manager */
        $manager = $this->app->make(SagaManager::class);

        $this->info("Triggering compensation rollback for Saga [{$sagaId}]...");

        try {
            $instance = $manager->compensate($sagaId);

            if ($instance->isCompensated()) {
                $this->info("Saga [{$sagaId}] successfully compensated (rolled back)!");
            } else {
                $this->error("Saga compensation ended with state: " . $instance->state->value);
                if ($instance->error) {
                    $this->error("Error: " . $instance->error);
                }
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to compensate Saga: " . $e->getMessage());
            return 1;
        }
    }
}
