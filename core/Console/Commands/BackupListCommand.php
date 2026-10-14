<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/BackupListCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command listing existing backup snapshots and their metadata.
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

class BackupListCommand extends Command
{
    protected string $name = 'backup:list';
    protected string $description = 'List all available backup snapshots with file size and timestamp';

    public function handle(): int
    {
        $this->line("====================================================================");
        $this->line("   NetFusion Framework — Available Backup Snapshots");
        $this->line("====================================================================");

        /** @var BackupManager $backupManager */
        $backupManager = app(BackupManager::class);
        $backups = $backupManager->list();

        if (empty($backups)) {
            $this->line("No backup snapshots found in storage/backups.");
            return 0;
        }

        $this->line(sprintf("%-36s %-12s %-20s", "FILENAME", "SIZE", "CREATED AT"));
        $this->line(str_repeat("-", 70));

        foreach ($backups as $b) {
            $this->line(sprintf("%-36s %-12s %-20s", $b['filename'], $b['size_formatted'], $b['created_at']));
        }

        $this->line();
        $this->info("Total: " . count($backups) . " backup snapshot(s) stored.");
        return 0;
    }
}
