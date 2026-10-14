<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/LockCleanCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Prune expired distributed lock records.
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

use NetFusion\Core\Console\Command;
use NetFusion\Core\Lock\LockManager;

class LockCleanCommand extends Command
{
    protected string $name = 'lock:clean';
    protected string $description = 'Prune all expired distributed locks from storage.';

    public function handle(): int
    {
        /** @var LockManager $manager */
        $manager = $this->container->get('lock');
        $driver = $this->option('driver');

        $count = $manager->cleanExpired($driver ? (string)$driver : null);
        $this->info("Pruned {$count} expired lock(s).");
        return 0;
    }
}