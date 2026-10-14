<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/LockReleaseCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Force release a distributed lock by resource key.
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

class LockReleaseCommand extends Command
{
    protected string $name = 'lock:release';
    protected string $description = 'Release an active distributed lock by resource key.';

    public function handle(): int
    {
        $key = $this->argument('key');
        if (!$key) {
            $this->error('Please provide a lock key. Usage: php netfusion lock:release <key>');
            return 1;
        }

        /** @var LockManager $manager */
        $manager = $this->container->get('lock');
        $driver = $this->option('driver');

        $manager->driver($driver ? (string)$driver : null)->forceRelease((string)$key);
        $this->info("Lock [{$key}] was released successfully.");
        return 0;
    }
}