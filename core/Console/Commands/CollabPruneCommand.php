<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Collaboration\CollaborationManager;

/**
 * Class CollabPruneCommand
 *
 * CLI: php netfusion collab:prune [--timeout=30]
 */
class CollabPruneCommand extends Command
{
    protected string $name = 'collab:prune';
    protected string $description = 'Prune stale peer presences and inactive awareness sessions';

    public function handle(array $args = []): int
    {
        $timeout = 30.0;

        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--timeout=')) {
                $timeout = (float) substr($arg, 10);
            }
        }

        $manager = CollaborationManager::getInstance();
        $pruned = $manager->pruneStalePresences($timeout);

        $this->line("<info>[NetFusion Collaboration Presence Pruner]</info>");
        $this->line("Timeout Threshold : {$timeout}s");
        $this->line("Pruned Presences  : {$pruned}");
        $this->line("<info>Cleanup complete.</info>");

        return 0;
    }
}
