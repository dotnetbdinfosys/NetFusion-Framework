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

use NetFusion\Core\Auth\Policy\AccessManager;
use NetFusion\Core\Console\Command;
use Throwable;

class PolicyListCommand extends Command
{
    protected string $name = 'policy:list';
    protected string $description = 'List all registered PBAC authorization policies and rules';

    public function handle(): int
    {
        /** @var AccessManager $manager */
        $manager = $this->app->make(AccessManager::class);

        try {
            $policies = $manager->policyStore()->all();

            if (empty($policies)) {
                $this->info("No active authorization policies found.");
                return 0;
            }

            $this->info("=================== Registered PBAC Policies ===================");
            $this->line(sprintf("%-20s | %-25s | %-10s | %s", "Policy ID", "Name", "Priority", "Rules Count"));
            $this->line(str_repeat("-", 80));

            foreach ($policies as $p) {
                $this->line(sprintf(
                    "%-20s | %-25s | %-10d | %d rules",
                    substr($p->id, 0, 20),
                    substr($p->name, 0, 25),
                    $p->priority,
                    $p->count()
                ));
            }
            $this->line(str_repeat("-", 80));
            $this->line("Total Policies: " . count($policies));

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to list policies: " . $e->getMessage());
            return 1;
        }
    }
}
