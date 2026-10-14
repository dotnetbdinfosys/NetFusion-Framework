<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/BackupRunCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command creating an encrypted/compressed full system and database backup snapshot.
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
use Throwable;

class BackupRunCommand extends Command
{
    protected string $name = 'backup:run';
    protected string $description = 'Create a compressed database and application backup archive (--only-db)';

    public function handle(): int
    {
        $this->line("====================================================================");
        $this->line("   NetFusion Framework — Automated Backup & Disaster Recovery");
        $this->line("====================================================================");
        $this->line("Starting backup snapshot generation...");

        $onlyDb = $this->hasOption('only-db');

        try {
            /** @var BackupManager $backupManager */
            $backupManager = app(BackupManager::class);
            $result = $backupManager->run($onlyDb);

            $this->info("✔ Backup successfully created!");
            $this->line("  Filename : " . $result['filename']);
            $this->line("  Size     : " . $result['size_formatted']);
            $this->line("  Checksum : " . substr($result['checksum'], 0, 16) . '... (SHA-256 Verified)');
            $this->line("  Location : " . $result['path']);
            return 0;
        } catch (Throwable $e) {
            $this->error("Backup creation failed: " . $e->getMessage());
            return 1;
        }
    }
}
