<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;

/**
 * CLI command to list children of a ZNode.
 * Usage: php netfusion zk:ls <path>
 */
class ZkLsCommand extends Command
{
    protected string $name = 'zk:ls';
    protected string $description = 'List children names of a ZNode';

    public function handle(array $args = []): int
    {
        $path = $args[0] ?? ($this->arguments[0] ?? '/');

        $zk = function_exists('zookeeper') ? zookeeper() : $this->app->make(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class);

        try {
            $children = $zk->getChildren($path);
            $this->info("Children for [{$path}]:");
            $this->line("[" . implode(', ', $children) . "]");
            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
