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
use NetFusion\Core\Etcd\Models\WatchEvent;

/**
 * Class EtcdWatchCommand
 *
 * CLI: php netfusion etcd:watch <key> [--prefix] [--rev=N]
 */
class EtcdWatchCommand extends Command
{
    protected string $name = 'etcd:watch';
    protected string $description = 'Watch event stream on a key or prefix from the etcd MVCC store';

    public function handle(array $args = []): int
    {
        $key = null;
        $isPrefix = false;
        $rev = 0;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if ($arg === '--prefix') {
                $isPrefix = true;
            } elseif (str_starts_with($arg, '--rev=')) {
                $rev = (int) substr($arg, 6);
            } elseif ($arg === '--rev' && isset($args[$i + 1])) {
                $rev = (int) $args[++$i];
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $key = $positional[0] ?? null;

        if ($key === null) {
            $this->error('Usage: php netfusion etcd:watch <key> [--prefix] [--rev=N]');
            return 1;
        }

        $etcd = function_exists('etcd') ? etcd() : $this->app->make(\NetFusion\Core\Etcd\EtcdManager::class);

        $this->info("Watching key '{$key}' (prefix: " . ($isPrefix ? 'true' : 'false') . ", startRev: {$rev})...");

        $watcher = $etcd->watch(
            $key,
            function (WatchEvent $event) {
                $k = $event->kv?->key ?? $event->prevKv?->key ?? 'unknown';
                $v = $event->kv?->value ?? '(null)';
                $r = $event->getRevision();
                $this->line("[EVENT {$event->type->value}] {$k} => {$v} (Revision: {$r})");
            },
            $isPrefix,
            $rev,
            true
        );

        $this->info("Watcher #{$watcher->id} initialized successfully.");
        return 0;
    }
}
