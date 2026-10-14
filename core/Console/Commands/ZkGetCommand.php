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
 * CLI command to get data and stat metadata of a ZNode.
 * Usage: php netfusion zk:get <path> [--watch]
 */
class ZkGetCommand extends Command
{
    protected string $name = 'zk:get';
    protected string $description = 'Get payload data and stat metadata of a ZNode';

    public function handle(array $args = []): int
    {
        $path = $args[0] ?? ($this->arguments[0] ?? null);

        if ($path === null) {
            $this->error('Usage: php netfusion zk:get <path>');
            return 1;
        }

        $zk = function_exists('zookeeper') ? zookeeper() : $this->app->make(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class);

        try {
            $stat = null;
            $data = $zk->getData($path, null, $stat);

            $this->info("Data: " . ($data ?? 'null'));
            $this->line("cZxid = " . $stat->czxid);
            $this->line("ctime = " . date('Y-m-d H:i:s', $stat->ctime));
            $this->line("mZxid = " . $stat->mzxid);
            $this->line("mtime = " . date('Y-m-d H:i:s', $stat->mtime));
            $this->line("dataVersion = " . $stat->version);
            $this->line("cversion = " . $stat->cversion);
            $this->line("ephemeralOwner = " . $stat->ephemeralOwner);
            $this->line("dataLength = " . $stat->dataLength);
            $this->line("numChildren = " . $stat->numChildren);

            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
