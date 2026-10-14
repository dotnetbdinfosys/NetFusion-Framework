<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/AuditPruneCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command pruning expired audit trail entries to maintain database performance.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Audit\AuditManager;
use NetFusion\Core\Console\Command;

class AuditPruneCommand extends Command
{
    protected string $name = 'audit:prune';
    protected string $description = 'Prune old audit logs and revision entries older than specified days (--days=90)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? 90);
        if ($days < 1) {
            $this->error("The --days option must be a positive integer.");
            return 1;
        }

        $this->line("Pruning audit logs older than {$days} days...");

        try {
            /** @var AuditManager $audit */
            $audit = app(AuditManager::class);
            $deleted = $audit->prune($days);

            $this->info("✔ Successfully pruned {$deleted} audit log record(s).");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Database error while pruning audit logs: " . $e->getMessage());
            return 1;
        }
    }
}
