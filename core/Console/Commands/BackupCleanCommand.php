<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/BackupCleanCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command pruning old backup snapshots based on retention rules.
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

use NetFusion\Core\Backup\BackupManager;
use NetFusion\Core\Console\Command;

class BackupCleanCommand extends Command
{
    protected string $name = 'backup:clean';
    protected string $description = 'Clean old backup archives keeping only the most recent N backups (--keep=7)';

    public function handle(): int
    {
        $keep = (int) ($this->option('keep') ?? 7);
        if ($keep < 1) {
            $this->error("The --keep option must be at least 1.");
            return 1;
        }

        $this->line("Cleaning old backup archives (retention: keep {$keep})...");

        /** @var BackupManager $backupManager */
        $backupManager = app(BackupManager::class);
        $deleted = $backupManager->clean($keep);

        $this->info("✔ Cleaned {$deleted} older backup archive(s).");
        return 0;
    }
}
