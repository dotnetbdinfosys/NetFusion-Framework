<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/LockListCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : List all active distributed locks.
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

class LockListCommand extends Command
{
    protected string $name = 'lock:list';
    protected string $description = 'List all currently active distributed locks.';

    public function handle(): int
    {
        /** @var LockManager $manager */
        $manager = $this->container->get('lock');
        $driver = $this->option('driver');

        $locks = $manager->listLocks($driver ? (string)$driver : null);

        if (empty($locks)) {
            $this->info('No active locks found.');
            return 0;
        }

        $this->info(sprintf('%-35s | %-32s | %-10s | %-20s', 'Lock Key', 'Owner', 'TTL (s)', 'Created At'));
        $this->info(str_repeat('-', 108));

        $now = time();
        foreach ($locks as $lock) {
            $ttl = max(0, (int)$lock['expiration'] - $now);
            $this->info(sprintf(
                '%-35s | %-32s | %-10d | %-20s',
                $lock['key'],
                $lock['owner'],
                $ttl,
                $lock['created_at'] ?? ''
            ));
        }

        return 0;
    }
}