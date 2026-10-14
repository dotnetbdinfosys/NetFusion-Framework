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
use NetFusion\Core\ZooKeeper\Enums\CreateMode;

/**
 * CLI command to create a ZNode in ZooKeeper namespace.
 * Usage: php netfusion zk:create <path> [data] [--ephemeral] [--sequential]
 */
class ZkCreateCommand extends Command
{
    protected string $name = 'zk:create';
    protected string $description = 'Create a persistent or ephemeral ZNode in ZooKeeper namespace';

    public function handle(array $args = []): int
    {
        $path = null;
        $data = null;
        $isEphemeral = false;
        $isSequential = false;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if ($arg === '--ephemeral' || $arg === '-e') {
                $isEphemeral = true;
            } elseif ($arg === '--sequential' || $arg === '-s') {
                $isSequential = true;
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $path = $positional[0] ?? null;
        $data = $positional[1] ?? null;

        if ($path === null) {
            $this->error('Usage: php netfusion zk:create <path> [data] [--ephemeral] [--sequential]');
            return 1;
        }

        $mode = CreateMode::PERSISTENT;
        if ($isEphemeral && $isSequential) {
            $mode = CreateMode::EPHEMERAL_SEQUENTIAL;
        } elseif ($isEphemeral) {
            $mode = CreateMode::EPHEMERAL;
        } elseif ($isSequential) {
            $mode = CreateMode::PERSISTENT_SEQUENTIAL;
        }

        $zk = function_exists('zookeeper') ? zookeeper() : $this->app->make(\NetFusion\Core\ZooKeeper\ZooKeeperManager::class);

        try {
            $createdPath = $zk->create($path, $data, $mode);
            $this->info("Created {$mode->name} node: {$createdPath}");
            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
